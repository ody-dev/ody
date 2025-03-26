# amqp

# Pull the official RabbitMQ image with management plugin

docker pull rabbitmq:3-management

# Run RabbitMQ container

```shell
docker run -d --name rabbitmq \
-p 5672:5672 \
-p 15672:15672 \
-e RABBITMQ_DEFAULT_USER=admin \
-e RABBITMQ_DEFAULT_PASS=password \
rabbitmq:3-management

FROM rabbitmq:3-management

RUN apt-get update && apt-get install -y curl

RUN curl -L https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/3.11.1/rabbitmq_delayed_message_exchange-3.11.1.ez > $RABBITMQ_HOME/plugins/rabbitmq_delayed_message_exchange-3.11.1.ez

RUN rabbitmq-plugins enable rabbitmq_delayed_message_exchange
```

```shell
version: '3.8'

services:
  rabbitmq:
    image: rabbitmq:3-management
    container_name: rabbitmq
    ports:
      - "5672:5672"   # AMQP protocol port
      - "15672:15672" # Management UI port
    environment:
      - RABBITMQ_DEFAULT_USER=admin
      - RABBITMQ_DEFAULT_PASS=password
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
      - rabbitmq_log:/var/log/rabbitmq
    restart: unless-stopped

volumes:
  rabbitmq_data:
  rabbitmq_log:
```