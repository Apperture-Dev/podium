FROM golang:1.23-bookworm AS builder
WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" \
    -o /out/activator ./cmd/activator

# distroless/static-nonroot: sin shell, sin gestor de paquetes, ya trae el
# bundle de CA certificates (necesario para cualquier egress HTTPS, p.ej. un
# futuro webhook de alerta) y el usuario "nonroot" (65532) preconfigurado —
# mismo criterio "no-root por defecto" que services/php.
FROM gcr.io/distroless/static-debian12:nonroot AS prod

COPY --from=builder /out/activator /activator

EXPOSE 8080 9090

ENTRYPOINT ["/activator"]
