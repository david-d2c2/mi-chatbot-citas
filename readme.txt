=== Mi Chatbot Citas ===
Contributors: 
Tags: chatbot, booking, calendar, openai, google-calendar
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chatbot de reservas para WordPress con shortcode, OpenAI y Google Calendar.

Repositorio del plugin: https://github.com/david-d2c2/mi-chatbot-citas

== Description ==

Mi Chatbot Citas permite insertar un widget de chat en WordPress para captar datos de reserva, consultar disponibilidad y crear eventos en Google Calendar.

Características principales:

- shortcode [chatbot_citas]
- shortcode [chatbot_citas mode="floating"]
- panel de ajustes en WordPress
- integración servidor con OpenAI
- integración servidor con Google Calendar
- consulta de huecos disponibles
- creación de eventos

== Installation ==

1. Sube el ZIP desde Plugins > Añadir nuevo > Subir plugin o copia la carpeta en wp-content/plugins/.
2. Activa el plugin.
3. Ve a Ajustes > Mi Chatbot Citas.
4. Introduce las credenciales de OpenAI y Google.
5. Inserta el shortcode en una página.

== Frequently Asked Questions ==

= ¿Necesito OpenAI para que funcione? =

Sí para el comportamiento completo. Si no configuras OpenAI, el plugin usará un análisis básico de respaldo.

= ¿Qué calendario usa? =

El que indiques en Google Calendar ID. Si pones primary, usa el calendario principal de la cuenta autorizada.

= ¿Incluye cancelación y reprogramación? =

No en la versión 1.0.0.

== Changelog ==

= 1.0.0 =
* Primera versión estable del plugin.
* Widget frontend con shortcode.
* Ajustes en admin.
* Consulta de disponibilidad.
* Creación de citas en Google Calendar.
