# Plantilla de build para lang=python, framework=fastapi.
# Convención sobre configuración (ver docs/examples/podium-example.yaml):
# dependencias en requirements.txt y la aplicación ASGI expuesta como `app`
# en main.py. Sin comando configurable.
#
# uvicorn se instala aparte de requirements.txt a propósito: es el servidor
# que pone la plantilla (igual que nginx en las de React/Vue), y un
# requirements.txt de FastAPI no siempre lo declara.

FROM python:3.13-slim
WORKDIR /app
ENV PYTHONUNBUFFERED=1
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt \
    && pip install --no-cache-dir "uvicorn[standard]"
COPY . .
EXPOSE 8000
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
