# Mi Chatbot Citas — WordPress plugin v1.0.0

> **Estado del proyecto:** en desarrollo activo  
> **Versión actual:** 1.0.0  
> Esta es una primera versión funcional del plugin. La base está operativa, pero el proyecto sigue evolucionando y requiere pruebas, ajustes y validación adicional antes de considerarse cerrado para producción.
Este plugin permite integrar un chatbot de reservas en WordPress mediante shortcode, conectado a OpenAI para la conversación y a Google Calendar para la gestión de disponibilidad y creación de citas.


## Repositorio

- Plugin URI: https://github.com/david-d2c2/mi-chatbot-citas
- Autor: David Caraballo_D2C2
- Author URI: https://github.com/david-d2c2

## Qué incluye

- Shortcode para incrustar el chatbot en cualquier página o entrada.
- Widget en frontend con HTML, CSS y JavaScript.
- Endpoint REST propio dentro de WordPress.
- Lógica del chatbot en servidor.
- Consulta de disponibilidad en Google Calendar.
- Creación de eventos en Google Calendar.
- Panel de ajustes en WordPress para configurar credenciales y horarios.
- Bloque visual de estado para saber qué falta antes de entregar o publicar.

## Alcance real de la versión 1.0.0

Esta versión está pensada como **MVP entregable**:

- recoge servicio, nombre, email y fecha
- pregunta preferencia horaria
- consulta huecos disponibles
- ofrece varias opciones
- confirma y crea el evento

No incluye todavía:

- cancelación de citas
- reprogramación
- panel de logs
- email automático
- generación de Google Meet
- flujo OAuth guiado dentro del plugin

## Requisitos

- WordPress 6.x o superior
- PHP 8.0 o superior recomendado
- Cuenta de OpenAI con API key activa
- Proyecto de Google Cloud con Google Calendar API habilitada
- Refresh token válido de Google

## Estructura del plugin

```txt
mi-chatbot-citas/
├── mi-chatbot-citas.php
├── readme.txt
├── README.md
├── assets/
│   ├── css/widget.css
│   └── js/widget.js
├── includes/
│   ├── class-settings.php
│   ├── class-shortcode.php
│   ├── class-rest.php
│   ├── class-openai.php
│   ├── class-google-calendar.php
│   └── class-slots.php
└── templates/
    └── widget.php
```

## Instalación

1. Sube la carpeta `mi-chatbot-citas` a `/wp-content/plugins/` o instala el ZIP desde **Plugins > Añadir nuevo > Subir plugin**.
2. Activa el plugin.
3. Ve a **Ajustes > Mi Chatbot Citas**.
4. Rellena los campos obligatorios.
5. Inserta el shortcode en la página donde deba aparecer el chatbot.

## Shortcodes

### Inline

```txt
[chatbot_citas]
```

### Flotante

```txt
[chatbot_citas mode="floating"]
```

## Configuración del plugin

En **Ajustes > Mi Chatbot Citas** encontrarás estos grupos de campos.

### Negocio y widget
- Nombre del negocio
- Mensaje de bienvenida
- Título del widget
- Texto del botón
- Color principal

### OpenAI
- OpenAI API key
- Modelo OpenAI

### Google Calendar
- Google Client ID
- Google Client Secret
- Google Refresh Token
- Google Calendar ID

### Agenda
- Zona horaria
- Horario laboral
- Duración de cita
- Margen entre citas
- Intervalo entre propuestas
- Número de huecos a ofrecer
- Opción para pedir teléfono

---

# Cómo conseguir la API key de OpenAI

## Qué debe hacer el cliente

Lo correcto es que **el cliente final genere su propia clave**. No uses una clave tuya en una instalación ajena. La clave se guarda en el servidor, no en el navegador.

## Pasos

1. Entrar en la plataforma de OpenAI.
2. Seleccionar el proyecto correcto.
3. Ir a la sección de API keys.
4. Crear una nueva secret key.
5. Guardarla en un lugar seguro.
6. Pegarla en el campo **OpenAI API key** del plugin.

## Recomendaciones

- No incrustar la clave en JavaScript.
- No enviarla por email sin protección.
- Si el cliente cambia de cuenta o de proyecto, actualizar la clave.
- Si sospechas que se ha filtrado, revocarla y generar otra.

---

# Cómo conseguir las credenciales de Google Calendar

Para que el plugin pueda leer disponibilidad y crear eventos necesitas estos datos:

1. Google Client ID
2. Google Client Secret
3. Google Refresh Token
4. Google Calendar ID

## Resumen del proceso

1. Crear o elegir un proyecto en Google Cloud.
2. Activar Google Calendar API.
3. Configurar la pantalla de consentimiento OAuth.
4. Crear un OAuth Client ID.
5. Obtener un refresh token.
6. Copiar el Calendar ID.

## 1) Crear proyecto en Google Cloud

- Entra en Google Cloud Console.
- Crea un proyecto nuevo o usa uno existente.
- Trabaja siempre dentro del proyecto correcto para no mezclar credenciales.

## 2) Activar Google Calendar API

- Ve a **APIs y servicios > Biblioteca**.
- Busca **Google Calendar API**.
- Pulsa **Habilitar**.

## 3) Configurar la pantalla de consentimiento OAuth

- Ve a **APIs y servicios > Pantalla de consentimiento OAuth**.
- Define nombre de la aplicación, correo de soporte y tipo de usuario.
- Completa la información mínima requerida.

### Advertencia importante

Si el proyecto está en modo de pruebas y usa permisos sensibles de Calendar, el refresh token puede durar poco o requerir volver a generarlo. No es un bug del plugin: es Google haciendo de Google.

## 4) Crear el OAuth Client ID

- Ve a **APIs y servicios > Credenciales**.
- Crea una credencial de tipo **OAuth client ID**.
- Selecciona **Web application**.
- Guarda el **Client ID** y el **Client Secret**.

## 5) Obtener el Refresh Token con OAuth Playground

La forma más rápida para esta versión es usar el **OAuth 2.0 Playground** de Google.

### Pasos exactos

1. Abre el OAuth Playground.
2. En el icono de ajustes, activa **Use your own OAuth credentials**.
3. Pega el **Client ID** y el **Client Secret** del proyecto.
4. Añade este scope:

```txt
https://www.googleapis.com/auth/calendar
```

5. Pulsa **Authorize APIs**.
6. Elige la cuenta con acceso al calendario.
7. Acepta permisos.
8. Pulsa **Exchange authorization code for tokens**.
9. Copia el valor de **refresh_token**.
10. Pégalo en el campo **Google Refresh Token** del plugin.

### Importante

- El refresh token debe corresponder a la cuenta que realmente tiene acceso al calendario.
- Si usas un calendario compartido, esa cuenta debe tener permisos suficientes.
- Si el token deja de funcionar, genera uno nuevo y reemplázalo.

## 6) Conseguir el Google Calendar ID

### Opción rápida

Usar:

```txt
primary
```

### Opción específica

Si quieres usar otro calendario:

1. Abre Google Calendar.
2. Ve a los ajustes del calendario concreto.
3. Busca la dirección o el ID del calendario.
4. Copia ese valor.
5. Pégalo en el plugin.

---

# Configuración mínima recomendada para una entrega a cliente

## OpenAI
- API key propia del cliente
- Modelo: `gpt-5-mini`

## Google
- Client ID propio del cliente
- Client Secret propio del cliente
- Refresh Token generado con la cuenta del cliente
- Calendar ID: `primary` si no hace falta separar calendarios

## Agenda
- Zona horaria correcta del negocio
- Horario laboral real
- Duración de cita real
- Buffer suficiente entre citas

---

# Checklist antes de entregar

- [ ] El plugin está activo
- [ ] El shortcode aparece en la página correcta
- [ ] La API key de OpenAI está puesta
- [ ] El Client ID y Client Secret de Google están puestos
- [ ] El Refresh Token funciona
- [ ] El Calendar ID es correcto
- [ ] El horario y duración están bien configurados
- [ ] El widget responde en frontend
- [ ] Se puede crear una cita de prueba

---

# Limitaciones conocidas de la v1.0.0

- Si no configuras OpenAI, el plugin seguirá usando un parser de respaldo muy básico.
- El parser de respaldo entiende mejor fechas en formato `YYYY-MM-DD`.
- No hay panel de auditoría ni histórico visual de reservas.
- No hay gestión de cancelación o cambio de citas.
- El flujo OAuth no está embebido en el admin; el refresh token se pega manualmente.

---

# Siguientes mejoras razonables

- Cancelar y reprogramar citas
- Emails automáticos de confirmación
- Google Meet
- Varios servicios con distinta duración
- Varios calendarios
- Registro de conversaciones y eventos
- Flujo OAuth guiado en el panel

---

# Entrega recomendada a tercero

Entrega siempre estos elementos:

1. ZIP instalable del plugin
2. Carpeta fuente del plugin
3. Este README
4. Un pequeño documento o email con:
   - shortcode a usar
   - página donde va insertado
   - qué credenciales debe aportar el cliente

Así evitas que te escriban dentro de dos semanas preguntando por qué “el bot habla muy bien, pero no agenda nada”.
