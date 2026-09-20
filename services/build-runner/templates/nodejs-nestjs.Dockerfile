# Plantilla de build para lang=nodejs, framework=nestjs.
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# npm ci && npm run build, arranca con npm run start:prod. Sin comando
# configurable.
#
# start:prod y no start: en todo proyecto generado con el CLI de Nest,
# `npm start` es `nest start`, que recompila con el CLI y arranca en modo
# desarrollo. Funciona, pero deja el CLI en el camino de arranque de cada
# pod y recompila TypeScript en cada reinicio. `start:prod` es `node
# dist/main`, el arranque que el propio scaffolding de Nest reserva para
# producción.

FROM node:22-slim AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:22-slim
WORKDIR /app
ENV NODE_ENV=production
COPY --from=build /app ./
EXPOSE 3000
CMD ["npm", "run", "start:prod"]
