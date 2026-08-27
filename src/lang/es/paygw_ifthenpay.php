<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'paygw_ifthenpay', language 'es'
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Default.
$string['pluginname'] = 'ifthenpay';
// Ver la nota en la versión inglesa: el logotipo ya identifica la marca, por lo que la descripción
// indica únicamente las formas de pago disponibles.
$string['gatewayname'] = 'ifthenpay';
$string['gatewaydescription'] =
    '<span class="d-block mb-1">Pague de forma segura con tarjeta, Apple Pay, Google Pay, MB WAY, Multibanco, ' .
    'Payshop o Pix.</span>' .
    '<span class="d-block small text-muted">Los métodos disponibles dependen de los que su proveedor haya activado.</span>';


// Modal (moustache).
$string['modal:redirectingtoifthenpay'] = 'Le estamos llevando a ifthenpay para completar su pago…';
// Settings / headings.
$string['api_heading'] = 'Conecte su cuenta de ifthenpay';
$string['behavior_heading'] = 'Comportamiento del pago';
$string['behavior_desc'] = 'Ajustes opcionales que afectan a cómo se muestra esta pasarela a los usuarios.';
// Onboarding y consejos. La estructura (lista, numeración, insignias) vive en la plantilla steps —
// estas cadenas contienen solo texto, para que un traductor nunca tenga que conservar clases Bootstrap.
$string['onboarding_step1'] =
    '<a href="https://ifthenpay.com/aderir/" target="_blank" rel="noopener">Suscríbase y firme el contrato</a>, ' .
    'seleccionando los métodos de pago que desea aceptar.';
$string['onboarding_step2'] = 'Una vez contratado, recibirá automáticamente una Backoffice Key: introdúzcala abajo.';
$string['onboarding_step3'] =
    'Solicite al <a href="mailto:suporte@ifthenpay.com">soporte de ifthenpay</a> una Gateway Key ' .
    '<strong>para Moodle</strong>, con los métodos de pago elegidos activados.';
$string['onboarding_step4'] = 'Todo lo demás se configura aquí, en Moodle.';
$string['onboarding_more_info'] =
    '¿Ya tiene contrato? Solo tiene que solicitar la Gateway Key. Más información en ' .
    '<a href="https://ifthenpay.com" target="_blank" rel="noopener">ifthenpay.com</a>.';
$string['moodle_payment_tips_title'] = '¿Nuevo en los pagos de Moodle?';
$string['moodle_tip1'] =
    'Cree una Cuenta de pago: <em>Administración del sitio → Pagos → Cuentas de pago → ' .
    'Crear cuenta de pago</em>.';
$string['moodle_tip2'] = 'Active la pasarela ifthenpay en esa cuenta (abajo).';
$string['moodle_tip3'] =
    'Active la <em>Inscripción mediante pago</em>: <em>Administración del sitio → Extensiones → Inscripciones → ' .
    'Gestionar extensiones de inscripción</em>.';
$string['moodle_tip4'] =
    'Añádala a un curso: <em>Curso → Participantes → Métodos de inscripción → Añadir método</em>.';
$string['moodle_tips_links'] =
    '<a href="https://docs.moodle.org/501/en/Payment_gateways" target="_blank" rel="noopener">Payment gateways</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Set_up_payment" target="_blank" rel="noopener">Set up payment</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Enrolment_on_payment" target="_blank" rel="noopener">Enrolment on payment</a>';
$string['backoffice_key'] = 'Backoffice Key';
$string['backoffice_key_desc'] = 'Se utiliza para autenticar las llamadas a la API y los webhooks.';
$string['methods_showcase_title'] = 'Métodos de pago admitidos';
$string['status_unconfigured_title'] = 'Aún sin conexión';
$string['status_unconfigured_desc'] =
    'ifthenpay es un servicio gratuito. Cuatro pasos para empezar a aceptar pagos.';
$string['status_connected_title'] = 'Conectado a ifthenpay';
$string['status_connected_desc'] =
    'Su Backoffice Key está configurada y ifthenpay está listo para aceptar pagos. ¿Necesita activar otro método de pago? ' .
    '<a href="mailto:suporte@ifthenpay.com">Contacte con el soporte de ifthenpay</a>.';
$string['onboarding_toggle'] = 'Mostrar los pasos de suscripción';
$string['status_nomoodlekeys_title'] = 'Todavía no hay ninguna Gateway Key para Moodle';
$string['status_nomoodlekeys_desc'] =
    'Su Backoffice Key es válida, pero todavía no tiene asignada ninguna Gateway Key con contexto Moodle, por lo que no ' .
    'hay nada que seleccionar en el formulario de la cuenta de pago. ' .
    '<a href="mailto:suporte@ifthenpay.com">Solicite una al soporte de ifthenpay</a>, con los métodos de pago ' .
    'elegidos activados.';
$string['status_rejected_title'] = 'Backoffice Key rechazada';
$string['status_rejected_desc'] =
    'ifthenpay no reconoció la Backoffice Key configurada abajo, por lo que no se pueden procesar pagos. ' .
    'Compruébela en su backoffice de ifthenpay o ' .
    '<a href="mailto:suporte@ifthenpay.com">contacte con el soporte de ifthenpay</a>.';

// Validation / messages.
$string['error_invalidformat'] = 'Formato no válido. Use 1234-5678-9012-3456.';
$string['error_invalid_backoffice_key'] = 'La Backoffice Key no es válida. Compruébela e inténtelo de nuevo.';
$string['error_missing_backoffice_key'] =
    'La Backoffice Key no está configurada. Defínala en los ajustes de la pasarela.';


// Errors for API responses.
$string['api:nobackofficekey_error'] = 'API: no hay ninguna Backoffice Key configurada.';
$string['api:error_invalid_pbl_response'] = 'Respuesta no válida de la API Pay-by-Link.';
$string['api:error_invalid_json_get'] = 'JSON no válido en la petición GET: {$a}';
$string['api:error_invalid_json_post'] = 'JSON no válido en la petición POST.';
$string['api:error_http_request_failed'] = 'La petición HTTP falló: {$a}';
$string['api:error_http_status'] = 'Error HTTP de la API: {$a}';
$string['api:error_unauthorized'] = 'La API rechazó las credenciales: {$a}';


// Form – sections & labels.
$string['form:gateway_key'] = 'Gateway Key';
$string['form:gateway_key_help'] =
    '¿Necesita otra key? <a href="mailto:suporte@ifthenpay.com">Contacte con el soporte de ifthenpay</a>. ' .
    'Las nuevas keys y cuentas aparecen automáticamente tras la activación.';

$string['form:payment_configuration'] = 'Métodos de pago';
$string['form:payment_configuration_reqnote'] = '<strong>Obligatorio:</strong> active al menos un método de pago.';
$string['form:method_not_activated'] =
    'No activado para esta Gateway Key &mdash; <a href="mailto:suporte@ifthenpay.com">solicite al soporte de ' .
    'ifthenpay</a> que lo añada.';
$string['form:gateway_key_no_methods'] =
    'Esta Gateway Key no tiene métodos de pago compatibles con esta extensión, por lo que no se puede activar ninguno ' .
    'abajo. Elija otra Gateway Key o <a href="mailto:suporte@ifthenpay.com">solicite al soporte de ifthenpay</a> que ' .
    'le añada métodos.';
$string['form:col_method'] = 'Método';
$string['form:col_account'] = 'Cuenta';
$string['form:col_default'] = 'Predeterminado';

$string['form:default_method'] = 'Método predeterminado (opcional)';
$string['form:enable_method'] = 'Activar {$a}';
$string['form:set_default_method'] = 'Definir {$a} como método predeterminado';
$string['form:default_unsupported'] = 'Este método de pago no puede preseleccionarse en el checkout.';
$string['form:default_method_help'] =
    'Opcional. Si se define, este método se preselecciona en el checkout cuando hay varios métodos activados. ' .
    'Déjelo en «Ninguno» para que el cliente elija sin preselección.';
$string['form:default_method_none'] = 'Ninguno';
$string['form:description'] = 'Descripción del checkout (opcional)';
$string['form:description_help'] = 'Texto opcional, de hasta 150 caracteres, que se muestra en el checkout.';

$string['form:missing_backoffice_key_inline'] =
    'La Backoffice Key no está configurada. <a href="{$a}">Abrir ajustes</a>.';
$string['form:rejected_backoffice_key_inline'] =
    'ifthenpay no reconoció la Backoffice Key configurada. <a href="{$a}">Abra los ajustes</a> para corregirla.';
$string['form:missing_gateway_keys_inline'] =
    'No hay ninguna Gateway Key configurada para Moodle en su backoffice de ifthenpay. ' .
    '<a href="mailto:suporte@ifthenpay.com">Contacte con el soporte de ifthenpay</a> para crear una Gateway Key para ' .
    'Moodle y asignarle los métodos de pago que quiera aceptar. Una vez creada, vuelva aquí y selecciónela.';

// Validation / messages.
$string['form:error_unavailable_enable'] =
    'Esta pasarela no se puede activar hasta que haya una Gateway Key para Moodle disponible. Desmarque esta casilla ' .
    'para guardar: la pasarela se desactiva y se conserva el resto de su configuración.';
$string['form:error_state_missing'] = 'Faltan los datos de configuración. Inténtelo de guardar de nuevo.';
$string['form:error_no_methods_enabled'] = 'Active al menos un método de pago.';
$string['form:error_default_not_enabled'] =
    'El método predeterminado «{$a}» debe estar activado en Métodos de pago.';
$string['form:error_default_unknown'] = 'El método predeterminado seleccionado «{$a}» no se reconoce.';
$string['form:error_maxchars'] = 'Máximo {$a} caracteres.';
$string['form:error_callback_activation'] =
    'No se pudieron activar las notificaciones de pago. Compruebe su Backoffice Key y su conexión a internet y ' .
    'guarde de nuevo. Error: {$a}';


// Proccessing => pay page.
$string['process:missing_ifthenpay_state'] =
    'No se encontraron datos de configuración de ifthenpay. Contacte con el administrador del sitio.';
$string['process:error_missing_redirect']  =
    'Falta la URL de redirección de ifthenpay. Contacte con el administrador del sitio.';

// Proccessing => cancel/error page.
$string['process:cancel_title']            = 'Pago no completado';
$string['process:cancel_desc_cancel']      = 'Canceló el pago antes de completarlo. No se ha cobrado nada.';
$string['process:cancel_desc_error']       = 'Algo salió mal al confirmar su pago. No se ha cobrado nada.';
$string['process:btn_try_again']           = 'Intentar de nuevo';
$string['process:btn_contact_support']     = 'Contactar con el soporte';
$string['process:not_found']               = 'No se encontró el intento de pago.';

// Processing => return page.
$string['process:return_title']            = 'Confirmando su pago';
$string['process:waiting_hint']            = 'Esto suele tardar unos segundos.';
$string['process:loading']                 = 'Comprobando';
$string['process:waiting_timeout']         =
    'Todavía en proceso. Puede cerrar esta página con seguridad: su inscripción se completa automáticamente en cuanto ' .
    'ifthenpay confirme el pago.';
$string['process:order_reference']         = 'Referencia del pedido';
$string['process:transaction_id']          = 'ID de transacción';
$string['process:amount']                  = 'Importe';
$string['process:btn_retry']               = 'Comprobar de nuevo';
$string['process:btn_go_to_courses']       = 'Ir a Mis cursos';


// Events.
$string['event:payment_problem'] = 'Problema de pago de ifthenpay';


// Privacy strings.
$string['privacy:metadata:ifthenpay_tx'] = 'Seguimiento mínimo de transacciones para la pasarela ifthenpay.';
$string['privacy:metadata:ifthenpay_tx:userid'] = 'ID de usuario asociado al intento de transacción.';
$string['privacy:metadata:ifthenpay_tx:timecreated'] = 'Cuándo se creó la transacción.';
$string['privacy:metadata:ifthenpay_tx:timemodified'] = 'Cuándo se actualizó la transacción por última vez.';
$string['privacy:metadata:ifthenpay_tx:token'] = 'Token aleatorio que identifica el intento de transacción.';
$string['privacy:metadata:ifthenpay_tx:component'] = 'Componente que inició el pago.';
$string['privacy:metadata:ifthenpay_tx:paymentarea'] = 'Área de pago dentro del componente.';
$string['privacy:metadata:ifthenpay_tx:itemid'] = 'Identificador del elemento dentro del área de pago.';
$string['privacy:metadata:ifthenpay_tx:accountid'] = 'Cuenta de ifthenpay utilizada para este pago.';
$string['privacy:metadata:ifthenpay_tx:amount'] = 'Importe del pago.';
$string['privacy:metadata:ifthenpay_tx:currency'] = 'Moneda del pago.';
$string['privacy:metadata:ifthenpay_tx:redirect_url'] = 'URL de retorno utilizada durante el flujo de pago.';
$string['privacy:metadata:ifthenpay_tx:transaction_id'] =
    'Identificador de transacción devuelto por ifthenpay (si está disponible).';
$string['privacy:metadata:ifthenpay_tx:paymentid'] = 'Enlace al registro de pago del núcleo.';
$string['privacy:metadata:ifthenpay_tx:state'] = 'Estado actual de la transacción.';
