FROM python:3.11-alpine
RUN apk add --no-cache wireguard-tools
WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . .
EXPOSE 8123
CMD ["python", "wg-simple-dash.py"]
