# Plantilla de build para lang=nodejs, framework=nextjs.
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# npm ci && npm run build, arranca con npm start (next start). Sin comando
# configurable. No asume output: "standalone" en next.config — el repo del
# tenant no está bajo nuestro control.

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
