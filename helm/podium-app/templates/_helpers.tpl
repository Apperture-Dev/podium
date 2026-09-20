{{/*
Nombre del Cluster de CNPG del servicio, y del Secret que CNPG genera para él.
CNPG llama al Secret "{cluster}-app" cuando la base y el rol son los del
bootstrap por defecto — mismo truco de naming que deploy/base/database.yaml, y
por eso el chart no necesita declarar ningún Secret propio.
*/}}
{{- define "podium-app.databaseCluster" -}}
{{ .Release.Name }}-db
{{- end -}}

{{- define "podium-app.databaseSecret" -}}
{{ include "podium-app.databaseCluster" . }}-app
{{- end -}}

{{- define "podium-app.databaseEnabled" -}}
{{- if ne .Values.database.mode "none" -}}true{{- end -}}
{{- end -}}
