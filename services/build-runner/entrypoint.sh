#!/usr/bin/env bash
set -euo pipefail

: "${SERVICE_NAME:?missing SERVICE_NAME}"
: "${PROJECT_ID:?missing PROJECT_ID}"
: "${VERSION:?missing VERSION}"
: "${COMMIT_ID:?missing COMMIT_ID}"
: "${REPOSITORY_URL:?missing REPOSITORY_URL}"
: "${IMAGE_REGISTRY:?missing IMAGE_REGISTRY}"

WORKDIR="/workspace/repo"

echo "==> cloning ${REPOSITORY_URL} @ ${COMMIT_ID}"
git clone --quiet "${REPOSITORY_URL}" "${WORKDIR}"
git -C "${WORKDIR}" checkout --quiet "${COMMIT_ID}"

PODIUM_YAML="${WORKDIR}/podium.yaml"
if [ ! -f "${PODIUM_YAML}" ]; then
    echo "podium.yaml not found at repo root" >&2
    exit 1
fi

SRC=$(yq -r ".${SERVICE_NAME}.src // \"null\"" "${PODIUM_YAML}")
LANG=$(yq -r ".${SERVICE_NAME}.lang // \"null\"" "${PODIUM_YAML}")
FRAMEWORK=$(yq -r ".${SERVICE_NAME}.framework // \"null\"" "${PODIUM_YAML}")

if [ "${SRC}" = "null" ] || [ "${LANG}" = "null" ] || [ "${FRAMEWORK}" = "null" ]; then
    echo "service '${SERVICE_NAME}' not found in podium.yaml, or missing src/lang/framework" >&2
    exit 1
fi

TEMPLATE_DOCKERFILE="/templates/${LANG}-${FRAMEWORK}.Dockerfile"
if [ ! -f "${TEMPLATE_DOCKERFILE}" ]; then
    echo "no build template for lang='${LANG}' framework='${FRAMEWORK}' (looked for ${TEMPLATE_DOCKERFILE})" >&2
    exit 1
fi

IMAGE_TAG="${IMAGE_REGISTRY}/${PROJECT_ID}-${SERVICE_NAME}:${VERSION}"

echo "==> building ${IMAGE_TAG} from ${TEMPLATE_DOCKERFILE} (context: ${WORKDIR}/${SRC})"
buildah bud --file "${TEMPLATE_DOCKERFILE}" --tag "${IMAGE_TAG}" "${WORKDIR}/${SRC}"

echo "==> pushing ${IMAGE_TAG}"
buildah push "${IMAGE_TAG}"

echo "==> done: ${IMAGE_TAG}"
# TODO (diferido, ver build/model.md "Frontera de infraestructura"): señalizar
# JobSucceeded/JobFailed de vuelta a Build en vez de terminar aquí.
