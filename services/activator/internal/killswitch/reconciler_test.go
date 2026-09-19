package killswitch_test

import (
	"context"
	"testing"

	networkingv1 "k8s.io/api/networking/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"

	fakeclient "k8s.io/client-go/kubernetes/fake"

	"github.com/Apperture-Dev/podium/services/activator/internal/killswitch"
	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
)

func tenantIngress(namespace, name, serviceName string, port int32) *networkingv1.Ingress {
	pathType := networkingv1.PathTypePrefix
	return &networkingv1.Ingress{
		ObjectMeta: metav1.ObjectMeta{Name: name, Namespace: namespace},
		Spec: networkingv1.IngressSpec{
			Rules: []networkingv1.IngressRule{{
				IngressRuleValue: networkingv1.IngressRuleValue{
					HTTP: &networkingv1.HTTPIngressRuleValue{
						Paths: []networkingv1.HTTPIngressPath{{
							PathType: &pathType,
							Backend: networkingv1.IngressBackend{
								Service: &networkingv1.IngressServiceBackend{
									Name: serviceName,
									Port: networkingv1.ServiceBackendPort{Number: port},
								},
							},
						}},
					},
				},
			}},
		},
	}
}

func backendOf(t *testing.T, ing *networkingv1.Ingress) (string, int32) {
	t.Helper()
	backend := ing.Spec.Rules[0].HTTP.Paths[0].Backend.Service
	return backend.Name, backend.Port.Number
}

func TestApplyRoutesThroughTheActivatorWhenEnabled(t *testing.T) {
	client := fakeclient.NewSimpleClientset(tenantIngress("team-a", "app-ingress", "app", 80))
	r := killswitch.NewReconciler(client, "activator", 8080)
	entries := []routing.Entry{{
		Host: "team-a.apperture.dev", Namespace: "team-a",
		IngressName: "app-ingress", ServiceName: "app", ServicePort: 80,
	}}

	if err := r.Apply(context.Background(), entries, true); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	ing, err := client.NetworkingV1().Ingresses("team-a").Get(context.Background(), "app-ingress", metav1.GetOptions{})
	if err != nil {
		t.Fatalf("unexpected error reading back ingress: %v", err)
	}
	name, port := backendOf(t, ing)
	if name != "activator" || port != 8080 {
		t.Fatalf("expected backend activator:8080, got %s:%d", name, port)
	}
}

func TestApplyRevertsToTheTenantServiceWhenDisabled(t *testing.T) {
	client := fakeclient.NewSimpleClientset(tenantIngress("team-a", "app-ingress", "activator", 8080))
	r := killswitch.NewReconciler(client, "activator", 8080)
	entries := []routing.Entry{{
		Host: "team-a.apperture.dev", Namespace: "team-a",
		IngressName: "app-ingress", ServiceName: "app", ServicePort: 80,
	}}

	if err := r.Apply(context.Background(), entries, false); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	ing, err := client.NetworkingV1().Ingresses("team-a").Get(context.Background(), "app-ingress", metav1.GetOptions{})
	if err != nil {
		t.Fatalf("unexpected error reading back ingress: %v", err)
	}
	name, port := backendOf(t, ing)
	if name != "app" || port != 80 {
		t.Fatalf("expected backend reverted to app:80, got %s:%d", name, port)
	}
}

func TestApplyContinuesPastAMissingIngressAndReportsIt(t *testing.T) {
	client := fakeclient.NewSimpleClientset(tenantIngress("team-b", "app-ingress", "app", 80))
	r := killswitch.NewReconciler(client, "activator", 8080)
	entries := []routing.Entry{
		{Host: "team-a.apperture.dev", Namespace: "team-a", IngressName: "missing-ingress", ServiceName: "app", ServicePort: 80},
		{Host: "team-b.apperture.dev", Namespace: "team-b", IngressName: "app-ingress", ServiceName: "app", ServicePort: 80},
	}

	err := r.Apply(context.Background(), entries, true)
	if err == nil {
		t.Fatal("expected an error reporting the missing ingress for team-a")
	}

	ing, getErr := client.NetworkingV1().Ingresses("team-b").Get(context.Background(), "app-ingress", metav1.GetOptions{})
	if getErr != nil {
		t.Fatalf("unexpected error reading back team-b ingress: %v", getErr)
	}
	name, _ := backendOf(t, ing)
	if name != "activator" {
		t.Fatalf("expected team-b to still be patched despite team-a failing, got backend %s", name)
	}
}
