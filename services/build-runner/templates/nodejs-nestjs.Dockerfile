# Plantilla de build para lang=nodejs, framework=nestjs.
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# npm ci && npm run build, arranca con npm start. Sin comando configurable.

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
CMD ["npm", "start"]
