# Plantilla de build para lang=rust, framework=cargo.
# "cargo" nombra la cadena de build, no una librería: esto compila igual un
# axum que un actix-web o un TcpListener a mano.
#
# Convención sobre configuración: crate en la raíz de `src` con un binario, y
# la app escuchando en :8080 (el chart publica ese puerto y no inyecta PORT).
#
# `cargo install --path .` en vez de `cargo build --release`: deja el binario
# en una ruta fija (/out/bin/) sin que la plantilla tenga que adivinar cómo se
# llama el paquete del equipo.

FROM rust:1-slim AS build
WORKDIR /src
COPY . .
RUN cargo install --path . --root /out --locked

FROM debian:trixie-slim
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/*
COPY --from=build /out/bin/ /usr/local/bin/
# El nombre del binario lo pone el equipo, así que se arranca el único que
# cargo instaló en lugar de codificar un nombre aquí.
EXPOSE 8080
CMD ["sh", "-c", "exec $(ls /usr/local/bin/* | head -n1)"]
