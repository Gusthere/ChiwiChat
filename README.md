# README - Backend de Mensajería Universitaria P2P Cifrada

## Descripción del Proyecto
Backend PHP basado en microservicios para una aplicación de mensajería punto a punto con cifrado. Este sistema proporciona:
- Autenticación segura de usuarios
- Gestión de conversaciones 1:1
- Cifrado de extremo a extremo (próxima implementación con OpenSSL)
- API RESTful para comunicación con clientes

## Requisitos Previos

### 1. Instalar WSL (Windows Subsystem for Linux)
```powershell
wsl --install
```
Reinicia tu computadora cuando termine la instalación.

### 2. Instalar Docker Desktop
Descarga e instala Docker Desktop desde [docker.com](https://www.docker.com/products/docker-desktop)

### 3. Instalar Git
```powershell
winget install --id Git.Git -e --source winget
```

## Configuración Inicial

1. Clona el repositorio:
```bash
git clone https://github.com/Gusthere/ChiwiChat.git
cd chiwichat
```

2. Crea el archivo `.env` copiando la plantilla:
```bash
cp .env.example .env
```

3. Edita el archivo `.env` con tus credenciales:
```ini
# Configuración de MySQL
MYSQL_ROOT_PASSWORD_USERS=tu_password_seguro
MYSQL_DATABASE_USERS=chiwichat_users
MYSQL_USER_USERS=usuario_app
MYSQL_PASSWORD_USERS=password_app

# Configuración de la aplicación
JWT_SECRET=tu_clave_secreta_jwt
```

## Instalación y Ejecución

1. Construye los contenedores:
```bash
docker-compose build
```

2. Inicia los servicios:
```bash
docker-compose up -d
```

3. Verifica que todos los contenedores estén saludables:
```bash
docker-compose ps
```

4. Ejecuta las migraciones de la base de datos:
```bash
docker-compose exec usuarios_service composer migrate
```

## Estructura del Proyecto

```
chiwichat-backend/
├── usuarios_service/       # Microservicio de autenticación y usuarios
│   ├── db/                # Migraciones y seeds
│   ├── src/               # Código fuente PHP
│   ├── phinx.php          # Configuración de migraciones
│   └── Dockerfile         # Configuración del contenedor
├── docker-compose.yml      # Orquestación de contenedores
└── .env                   # Variables de entorno
```

## Endpoints Principales

- `POST /api/auth/register` - Registro de usuarios
- `POST /api/auth/login` - Inicio de sesión
- `GET /api/users` - Búsqueda de usuarios
- `POST /api/conversations` - Gestión de conversaciones

## Comandos Útiles

### Migraciones
```bash
# Ejecutar migraciones
docker-compose exec usuarios_service composer migrate
```

### Gestión de Contenedores
```bash
# Detener todos los servicios
docker-compose down

# Reiniciar un servicio específico
docker-compose restart usuarios_service

# Ver logs en tiempo real
docker-compose logs -f usuarios_service
```

## Próximas Implementaciones
- Integración con OpenSSL para cifrado P2P
- Microservicio de mensajería independiente
- Soporte para grupos y mensajes multimedia
- Sistema de notificaciones push

## Solución de Problemas

Si encuentras errores al ejecutar las migraciones:
1. Verifica que la base de datos esté accesible:
```bash
docker-compose exec db_users mysql -u root -p$MYSQL_ROOT_PASSWORD_USERS -e "SHOW DATABASES;"
```

2. Revisa los logs del servicio:
```bash
docker-compose logs usuarios_service
```

3. Si es necesario, reconstruye todo:
```bash
docker-compose down -v
docker-compose up -d --build
```