# Plantilla de build para lang=nodejs, framework=vue.
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# npm ci && npm run build. El resultado es estático, así que no queda Node
# en tiempo de ejecución: lo sirve nginx en el puerto 80.
#
# dist/ con fallback a build/: Vue 3 con Vite emite dist/, pero un repo con
# otro bundler puede emitir build/. El `try_files ... /index.html` es lo que
# hace que una ruta de la SPA abierta en frío no devuelva 404.
#
# La configuración de nginx se escribe aquí y no se copia: el contexto de
# build es el repo del equipo, no este directorio de plantillas.

FROM node:22-slim AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build
RUN mkdir -p /out && cp -r dist/. /out 2>/dev/null || cp -r build/. /out

FROM nginx:alpine
COPY --from=build /out /usr/share/nginx/html
RUN printf 'server {\n  listen 80;\n  root /usr/share/nginx/html;\n  location / {\n    try_files $uri $uri/ /index.html;\n  }\n}\n' > /etc/nginx/conf.d/default.conf
EXPOSE 80
