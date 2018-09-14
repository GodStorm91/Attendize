
# Vietprojp Events

This is website for events organized by Vietpro, customized based on open source [Attendize](https://github.com/Attendize/Attendize)

# Development

## Prerequisites

- Install [docker](https://docs.docker.com/install/)
- Install [docker-compose](https://docs.docker.com/compose/install/)


## Setup local environment

- Create `.env` from `.env.example`
  ```bash
  cp .env.example .env
  ```
  
- Append config for `KOMOJU_MERCHANT_UUID` into `.env`
  
  ```bash
  KOMOJU_MERCHANT_UUID=xxxxxxxxx
  ```
  
- Start docker-compose and follow instructions in [Attendize](https://github.com/Attendize/Attendize)
  
  ```bash
  docker-compose up -d
  ```
  
  After that webserver is ready on localhost port 8080 and phpmyadmin is on port 8088

- Open `phpmyadmin` in [http://localhost:8088](http://localhost:8088)

- Execute SQL to insert `Komoju` into `payment_gateways` table
  
  ```sql
  INSERT INTO `payment_gateways`(`id`, `provider_name`, `provider_url`, `is_on_site`, `can_refund`, `name`) 
  VALUES (5, 'komoju', 'https://komoju.com', '0', '0', 'Komoju')
  ```

# Release