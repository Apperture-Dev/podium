// Command deploy-launcher consumes DeployAttemptRequested off Redis
// Streams, creates/updates the tenant's ArgoCD Application, and reports
// the result back as HealthCheckSucceeded/HealthCheckExhausted — pure
// orchestration, no independent logic lives here (see internal/* for the
// tested pieces this wires up), same philosophy as
// services/build-launcher/cmd/build-launcher/main.go.
package main

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"os"
	"os/signal"
	"strconv"
	"syscall"
	"time"

	"github.com/redis/go-redis/v9"
	"k8s.io/client-go/dynamic"
	"k8s.io/client-go/rest"
	"k8s.io/client-go/tools/clientcmd"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/argospec"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/launcher"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/messenger"
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

	redisAddr := envOrDefault("REDIS_ADDR", "redis:6379")
	requestStream := envOrDefault("DEPLOY_ATTEMPT_REQUESTED_STREAM", "podium.deploy-attempt-requested")
	succeededStream := envOrDefault("HEALTH_CHECK_SUCCEEDED_STREAM", "podium.health-check-succeeded")
	exhaustedStream := envOrDefault("HEALTH_CHECK_EXHAUSTED_STREAM", "podium.health-check-exhausted")
	group := envOrDefault("CONSUMER_GROUP", "deploy-launcher")
	consumer := envOrDefault("CONSUMER_NAME", "deploy-launcher-1")
	pollInterval := envDurationOrDefault("HEALTH_POLL_INTERVAL", 5*time.Second)
	maxAttempts := envIntOrDefault("HEALTH_MAX_ATTEMPTS", 3)

	cfg := argospec.Config{
		ArgoCDNamespace: envOrDefault("ARGOCD_NAMESPACE", "argocd"),
		ChartRepoURL:    envOrDefault("CHART_REPO_URL", "registry.apperture.dev/charts"),
		ChartName:       envOrDefault("CHART_NAME", "podium-app"),
		ChartVersion:    envOrDefault("CHART_VERSION", "0.1.0"),
		BaseDomain:      envOrDefault("BASE_DOMAIN", "apperture.dev"),
	}

	dynamicClient, err := newDynamicClient()
	if err != nil {
		logger.Error("building kubernetes dynamic client", "err", err)
		os.Exit(1)
	}

	rdb := redis.NewClient(&redis.Options{Addr: redisAddr})
	defer rdb.Close()

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	if err := ensureGroup(ctx, rdb, requestStream, group); err != nil {
		logger.Error("creating consumer group", "stream", requestStream, "group", group, "err", err)
		os.Exit(1)
	}

	l := launcher.New(dynamicClient, cfg, pollInterval, maxAttempts)

	logger.Info("deploy-launcher listening", "stream", requestStream, "group", group, "consumer", consumer)
	for ctx.Err() == nil {
		entries, err := rdb.XReadGroup(ctx, &redis.XReadGroupArgs{
			Group:    group,
			Consumer: consumer,
			Streams:  []string{requestStream, ">"},
			Count:    1,
			Block:    5 * time.Second,
		}).Result()
		if err != nil {
			if errors.Is(err, redis.Nil) || errors.Is(err, context.Canceled) {
				continue
			}
			logger.Error("reading from stream", "err", err)
			continue
		}

		for _, stream := range entries {
			for _, msg := range stream.Messages {
				handleMessage(ctx, logger, rdb, l, requestStream, group, succeededStream, exhaustedStream, msg)
			}
		}
	}
}

func handleMessage(
	ctx context.Context,
	logger *slog.Logger,
	rdb *redis.Client,
	l *launcher.Launcher,
	requestStream, group, succeededStream, exhaustedStream string,
	msg redis.XMessage,
) {
	raw, ok := msg.Values["message"].(string)
	if !ok {
		logger.Error("stream entry missing \"message\" field", "id", msg.ID)
		return
	}

	msgType, body, err := messenger.Decode([]byte(raw))
	if err != nil {
		logger.Error("decoding envelope", "id", msg.ID, "err", err)
		return
	}
	if msgType != events.DeployAttemptRequestedType {
		logger.Warn("ignoring unexpected message type", "id", msg.ID, "type", msgType)
		ackAndDelete(ctx, rdb, requestStream, group, msg.ID, logger)
		return
	}

	var req events.DeployAttemptRequested
	if err := json.Unmarshal(body, &req); err != nil {
		logger.Error("unmarshalling DeployAttemptRequested", "id", msg.ID, "err", err)
		return
	}

	succeeded, failed, err := l.Handle(ctx, req)
	if err != nil {
		logger.Error("handling deploy attempt", "deployAttemptId", req.DeployAttemptID, "err", err)
		return
	}

	if succeeded != nil {
		publish(ctx, rdb, succeededStream, events.HealthCheckSucceededType, succeeded, logger)
	} else {
		publish(ctx, rdb, exhaustedStream, events.HealthCheckExhaustedType, failed, logger)
	}

	ackAndDelete(ctx, rdb, requestStream, group, msg.ID, logger)
}

func publish(ctx context.Context, rdb *redis.Client, stream, msgType string, payload any, logger *slog.Logger) {
	body, err := json.Marshal(payload)
	if err != nil {
		logger.Error("marshalling result", "stream", stream, "err", err)
		return
	}
	envelope, err := messenger.Encode(msgType, body)
	if err != nil {
		logger.Error("encoding envelope", "stream", stream, "err", err)
		return
	}
	if err := rdb.XAdd(ctx, &redis.XAddArgs{Stream: stream, Values: map[string]any{"message": string(envelope)}}).Err(); err != nil {
		logger.Error("publishing result", "stream", stream, "err", err)
	}
}

// ackAndDelete mirrors PHP's own delete_after_ack/delete_after_reject
// default (both true, never overridden in messenger.yaml) — XDEL removes
// the entry from the stream regardless of which consumer group confirmed
// it, so this doesn't conflict with the "unassigned" placeholder group.
func ackAndDelete(ctx context.Context, rdb *redis.Client, stream, group, id string, logger *slog.Logger) {
	if err := rdb.XAck(ctx, stream, group, id).Err(); err != nil {
		logger.Error("acking message", "id", id, "err", err)
	}
	if err := rdb.XDel(ctx, stream, id).Err(); err != nil {
		logger.Error("deleting message", "id", id, "err", err)
	}
}

func ensureGroup(ctx context.Context, rdb *redis.Client, stream, group string) error {
	err := rdb.XGroupCreateMkStream(ctx, stream, group, "0").Err()
	if err != nil && !errors.Is(err, redis.Nil) {
		// BUSYGROUP means the group already exists — not an error.
		if err.Error() != "BUSYGROUP Consumer Group name already exists" {
			return err
		}
	}
	return nil
}

func newDynamicClient() (dynamic.Interface, error) {
	cfg, err := rest.InClusterConfig()
	if err != nil {
		kubeconfig := os.Getenv("KUBECONFIG")
		if kubeconfig == "" {
			kubeconfig = os.Getenv("HOME") + "/.kube/config"
		}
		cfg, err = clientcmd.BuildConfigFromFlags("", kubeconfig)
		if err != nil {
			return nil, err
		}
	}
	return dynamic.NewForConfig(cfg)
}

func envOrDefault(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func envIntOrDefault(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func envDurationOrDefault(key string, def time.Duration) time.Duration {
	if v := os.Getenv(key); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			return d
		}
	}
	return def
}
