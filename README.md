<div align="center">

# 💬 ChiwiChat

### Encrypted P2P University Messaging Platform · Plataforma de Mensajería Universitaria P2P Cifrada

[![PHP](https://img.shields.io/badge/PHP-8.3-8892BF?logo=php&logoColor=white)](https://www.php.net/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![MongoDB](https://img.shields.io/badge/MongoDB-4.4-47A248?logo=mongodb&logoColor=white)](https://www.mongodb.com/)
[![Apache](https://img.shields.io/badge/Apache-2.4-D22128?logo=apache&logoColor=white)](https://httpd.apache.org/)
[![JWT](https://img.shields.io/badge/JWT-Auth-000000?logo=jsonwebtokens&logoColor=white)](https://jwt.io/)
[![OpenSSL](https://img.shields.io/badge/OpenSSL-Encryption-721412?logo=openssl&logoColor=white)](https://www.openssl.org/)

</div>

---

## 🇬🇧 English

### Overview

**ChiwiChat** is a backend platform built with a **microservices architecture** for secure, encrypted peer-to-peer (P2P) messaging. Designed for university environments, it provides robust user authentication, real-time conversation management, end-to-end encryption using OpenSSL, and WebSocket support — all orchestrated via Docker Compose.

The system is composed of multiple independent repositories (Git submodules) that work together to deliver a cohesive and scalable messaging solution.

---

### 🏗️ Architecture

```
ChiwiChat/
├── usuarios_service/        # Users & Auth microservice (PHP + MySQL)
├── chats_service_mongo/     # Chats microservice (PHP + MongoDB)
├── ChiwiWebsocket/          # [Submodule] Real-time WebSocket server (Node.js)
├── ChiwiCrypt/              # [Submodule] End-to-end encryption service (PHP + OpenSSL)
├── docker-compose.yml       # Container orchestration
└── .env.example             # Environment variables template
```

> **⚙️ Submodule-based design:** `ChiwiWebsocket` and `ChiwiCrypt` are maintained as independent Git submodules, allowing them to evolve separately and be reused across other projects.

---

### 🧩 Services & Submodules

| Service | Technology | Description |
|---|---|---|
| `usuarios_service` | PHP 8.3 + MySQL 8 | User registration, login, JWT authentication |
| `chats_service_mongo` | PHP 8.3 + MongoDB 4.4 | Chat creation, message handling, conversation management |
| `ChiwiWebsocket` *(submodule)* | Node.js | Real-time bidirectional communication via WebSockets |
| `ChiwiCrypt` *(submodule)* | PHP + OpenSSL | Asymmetric key generation and message encryption/decryption |

---

### 🔧 Tech Stack

- **Runtime:** PHP 8.3 on Apache 2.4
- **Databases:** MySQL 8.0 (users) · MongoDB 4.4 (messages)
- **Auth:** JWT (`firebase/php-jwt`)
- **Validation:** `respect/validation`
- **Migrations:** Phinx (`robmorgan/phinx`)
- **Encryption:** OpenSSL via ChiwiCrypt
- **WebSockets:** Node.js via ChiwiWebsocket
- **Containerization:** Docker & Docker Compose
- **Image Registry:** Docker Hub (`gusher/chiwichat`)

---

### 🚀 Getting Started

#### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop) with WSL2 backend
- [Git](https://git-scm.com/) (with submodule support)

#### 1. Clone with Submodules

```bash
git clone --recurse-submodules https://github.com/Gusthere/ChiwiChat.git
cd ChiwiChat
```

> If you already cloned without `--recurse-submodules`, run:
> ```bash
> git submodule update --init --recursive
> ```

#### 2. Configure Environment Variables

```bash
cp .env.example .env
```

Edit `.env` with your credentials:

```ini
# MySQL – Users DB
MYSQL_ROOT_PASSWORD_USERS=your_secure_password
MYSQL_DATABASE_USERS=users_db

# MongoDB – Chats DB
MONGO_ROOT_PASSWORD=your_mongo_password
MONGO_DATABASE=chats_mongo

# JWT
JWT_SECRET=your_very_long_and_secret_jwt_key
```

#### 3. Start All Services

```bash
docker compose up -d
```

#### 4. Run Database Migrations

```bash
docker compose exec usuarios_service composer migrate
docker compose exec chats_service_mongo composer migrate
```

#### 5. Verify All Containers Are Healthy

```bash
docker compose ps
```

---

### 📡 API Endpoints

#### Users Service (`usuarios_service`)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/register` | Register a new user |
| `POST` | `/api/auth/login` | Authenticate and receive JWT |
| `GET` | `/api/users` | Search/list users |

#### Chats Service (`chats_service_mongo`)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/conversations` | Create a new conversation |
| `GET` | `/api/conversations/{id}` | Get messages from a conversation |
| `POST` | `/api/messages` | Send a message |

---

### 🐳 Useful Docker Commands

```bash
# Stop all services
docker compose down

# View real-time logs
docker compose logs -f usuarios_service

# Restart a specific service
docker compose restart chats_service_mongo

# Full reset (deletes volumes)
docker compose down -v && docker compose up -d --build
```

---

### 👥 Team

| Author | GitHub | Role |
|---|---|---|
| Gustavo Heredia | [@Gusthere](https://github.com/Gusthere) | Lead Backend – Users & Chats services |
| Diego Noss | [@diegonoss](https://github.com/diegonoss) | ChiwiCrypt – Encryption service |
| Jugney | [@jugneyidk](https://github.com/jugneyidk) | ChiwiWebsocket – Real-time WebSocket server |
| Samuel Pereira | [@shame652003](https://github.com/shame652003) | Backend contributor |
| Ana Wyatt | [@anawyatt](https://github.com/anawyatt) | Backend contributor |

---

## 🇪🇸 Español

### Descripción General

**ChiwiChat** es una plataforma backend construida con **arquitectura de microservicios** para mensajería cifrada punto a punto (P2P). Diseñada para entornos universitarios, ofrece autenticación robusta de usuarios, gestión de conversaciones en tiempo real, cifrado extremo a extremo con OpenSSL y soporte WebSocket — todo orquestado mediante Docker Compose.

El sistema está compuesto por múltiples repositorios independientes (submódulos de Git) que trabajan en conjunto para entregar una solución de mensajería cohesiva y escalable.

---

### 🏗️ Arquitectura

```
ChiwiChat/
├── usuarios_service/        # Microservicio de usuarios y autenticación (PHP + MySQL)
├── chats_service_mongo/     # Microservicio de chats (PHP + MongoDB)
├── ChiwiWebsocket/          # [Submódulo] Servidor WebSocket en tiempo real (Node.js)
├── ChiwiCrypt/              # [Submódulo] Servicio de cifrado E2E (PHP + OpenSSL)
├── docker-compose.yml       # Orquestación de contenedores
└── .env.example             # Plantilla de variables de entorno
```

> **⚙️ Diseño basado en submódulos:** `ChiwiWebsocket` y `ChiwiCrypt` se mantienen como submódulos Git independientes, lo que les permite evolucionar por separado y ser reutilizados en otros proyectos.

---

### 🧩 Servicios y Submódulos

| Servicio | Tecnología | Descripción |
|---|---|---|
| `usuarios_service` | PHP 8.3 + MySQL 8 | Registro de usuarios, login, autenticación JWT |
| `chats_service_mongo` | PHP 8.3 + MongoDB 4.4 | Creación de chats, mensajes, gestión de conversaciones |
| `ChiwiWebsocket` *(submódulo)* | Node.js | Comunicación bidireccional en tiempo real vía WebSockets |
| `ChiwiCrypt` *(submódulo)* | PHP + OpenSSL | Generación de claves asimétricas y cifrado/descifrado de mensajes |

---

### 🔧 Stack Tecnológico

- **Runtime:** PHP 8.3 sobre Apache 2.4
- **Bases de datos:** MySQL 8.0 (usuarios) · MongoDB 4.4 (mensajes)
- **Autenticación:** JWT (`firebase/php-jwt`)
- **Validación:** `respect/validation`
- **Migraciones:** Phinx (`robmorgan/phinx`)
- **Cifrado:** OpenSSL a través de ChiwiCrypt
- **WebSockets:** Node.js a través de ChiwiWebsocket
- **Contenedores:** Docker & Docker Compose
- **Registro de imágenes:** Docker Hub (`gusher/chiwichat`)

---

### 🚀 Puesta en Marcha

#### Requisitos Previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop) con backend WSL2
- [Git](https://git-scm.com/) (con soporte para submódulos)

#### 1. Clonar con Submódulos

```bash
git clone --recurse-submodules https://github.com/Gusthere/ChiwiChat.git
cd ChiwiChat
```

> Si ya clonaste sin `--recurse-submodules`, ejecuta:
> ```bash
> git submodule update --init --recursive
> ```

#### 2. Configurar Variables de Entorno

```bash
cp .env.example .env
```

Edita `.env` con tus credenciales:

```ini
# MySQL – Base de datos de usuarios
MYSQL_ROOT_PASSWORD_USERS=tu_contraseña_segura
MYSQL_DATABASE_USERS=users_db

# MongoDB – Base de datos de chats
MONGO_ROOT_PASSWORD=tu_contraseña_mongo
MONGO_DATABASE=chats_mongo

# JWT
JWT_SECRET=una_clave_secreta_muy_larga_y_segura
```

#### 3. Iniciar Todos los Servicios

```bash
docker compose up -d
```

#### 4. Ejecutar Migraciones

```bash
docker compose exec usuarios_service composer migrate
docker compose exec chats_service_mongo composer migrate
```

#### 5. Verificar que los Contenedores Están Saludables

```bash
docker compose ps
```

---

### 📡 Endpoints de la API

#### Servicio de Usuarios (`usuarios_service`)

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/api/auth/register` | Registrar un nuevo usuario |
| `POST` | `/api/auth/login` | Autenticarse y recibir JWT |
| `GET` | `/api/users` | Buscar/listar usuarios |

#### Servicio de Chats (`chats_service_mongo`)

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/api/conversations` | Crear una nueva conversación |
| `GET` | `/api/conversations/{id}` | Obtener mensajes de una conversación |
| `POST` | `/api/messages` | Enviar un mensaje |

---

### 🐳 Comandos Docker Útiles

```bash
# Detener todos los servicios
docker compose down

# Ver logs en tiempo real
docker compose logs -f usuarios_service

# Reiniciar un servicio específico
docker compose restart chats_service_mongo

# Reset completo (elimina volúmenes)
docker compose down -v && docker compose up -d --build
```

---

### 🔍 Solución de Problemas

**Las migraciones fallan:**
```bash
# Verificar que la base de datos está accesible
docker compose exec db_users mysql -u root -p -e "SHOW DATABASES;"

# Revisar los logs del servicio
docker compose logs usuarios_service
```

**Los contenedores no inician:**
```bash
# Reconstruir desde cero
docker compose down -v
docker compose up -d --build
```

**Los submódulos están vacíos:**
```bash
git submodule update --init --recursive
```

---

### 👥 Equipo

| Autor | GitHub | Rol |
|---|---|---|
| Gustavo Heredia | [@Gusthere](https://github.com/Gusthere) | Backend principal – Servicios de usuarios y chats |
| Diego Noss | [@diegonoss](https://github.com/diegonoss) | ChiwiCrypt – Servicio de cifrado |
| Jugney | [@jugneyidk](https://github.com/jugneyidk) | ChiwiWebsocket – Servidor WebSocket en tiempo real |
| Samuel Pereira | [@shame652003](https://github.com/shame652003) | Contribuidor backend |
| Ana Wyatt | [@anawyatt](https://github.com/anawyatt) | Contribuidora backend |

---

<div align="center">

Made at the university · Creado en la universidad

</div>