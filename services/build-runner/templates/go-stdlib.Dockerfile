# Plantilla de build para lang=go, framework=stdlib.
# "stdlib" nombra la cadena de build, no una librería: esto compila igual un
# net/http pelado que un gin, echo o chi por encima.
#
# Convención sobre configuración: módulo Go en la raíz de `src`, con el
# paquete main ahí mismo, y la app escuchando en :8080 (el chart publica ese
# puerto y no inyecta PORT).

FROM golang:1.25 AS build
WORKDIR /src
COPY go.mod go.sum* ./
RUN go mod download
COPY . .
# CGO desactivado para que el binario corra en la imagen distroless/static,
# que no lleva libc.
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/server .

FROM gcr.io/distroless/static-debian12
COPY --from=build /out/server /server
EXPOSE 8080
ENTRYPOINT ["/server"]
