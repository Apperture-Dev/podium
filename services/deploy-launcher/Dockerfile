FROM golang:1.23-bookworm AS builder
WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" \
    -o /out/deploy-launcher ./cmd/deploy-launcher

# distroless/static-nonroot: mismo criterio que services/activator y
# services/build-launcher — sin shell, sin gestor de paquetes, CA certs y
# usuario "nonroot" ya listos.
FROM gcr.io/distroless/static-debian12:nonroot AS prod

COPY --from=builder /out/deploy-launcher /deploy-launcher

ENTRYPOINT ["/deploy-launcher"]
