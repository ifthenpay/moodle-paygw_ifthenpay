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
 * Strings for component 'paygw_ifthenpay', language 'pt'
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Default.
$string['pluginname'] = 'ifthenpay';
// Ver a nota na versão inglesa: o logótipo já identifica a marca, por isso a descrição indica
// apenas as formas de pagamento disponíveis.
$string['gatewayname'] = 'ifthenpay';
$string['gatewaydescription'] =
    '<span class="d-block mb-1">Pague com seguran&ccedil;a por cart&atilde;o, Apple Pay, Google Pay, MB WAY, Multibanco, ' .
    'Payshop ou Pix.</span>' .
    '<span class="d-block small text-muted">Os m&eacute;todos dispon&iacute;veis dependem dos que o seu fornecedor ativou.</span>';


// Modal (moustache).
$string['modal:redirectingtoifthenpay'] = 'A encaminhá-lo para a ifthenpay para concluir o pagamento…';
// Settings / headings.
$string['api_heading'] = 'Ligação à ifthenpay';
$string['behavior_heading'] = 'Comportamento do pagamento';
$string['behavior_desc'] = 'Definições opcionais que afetam como esta gateway é apresentada aos utilizadores.';
// Onboarding e dicas. A estrutura (lista, numeração, badges) vive no template steps — estas
// strings têm apenas texto, para que um tradutor nunca tenha de preservar classes Bootstrap.
$string['onboarding_step1'] =
    '<a href="https://ifthenpay.com/aderir/" target="_blank" rel="noopener">Subscreva e assine o contrato</a>, ' .
    'selecionando os métodos de pagamento que pretende aceitar.';
$string['onboarding_step2'] = 'Após a contratação, receberá automaticamente uma Backoffice Key — insira-a em baixo.';
$string['onboarding_step3'] =
    'Solicite ao <a href="mailto:suporte@ifthenpay.com">suporte da ifthenpay</a> uma Gateway Key ' .
    '<strong>para Moodle</strong>, com os métodos de pagamento escolhidos ativados.';
$string['onboarding_step4'] = 'Todo o resto é configurado aqui no Moodle.';
$string['onboarding_more_info'] =
    'Já tem contrato? Basta solicitar a Gateway Key. Mais informação em ' .
    '<a href="https://ifthenpay.com" target="_blank" rel="noopener">ifthenpay.com</a>.';
$string['moodle_payment_tips_title'] = 'Novo nos pagamentos do Moodle?';
$string['moodle_tip1'] =
    'Crie uma Conta de Pagamento: <em>Administração do site → Pagamentos → Contas de pagamento → ' .
    'Criar conta de pagamento</em>.';
$string['moodle_tip2'] = 'Ative a gateway ifthenpay nessa conta (em baixo).';
$string['moodle_tip3'] =
    'Ative a <em>Inscrição mediante pagamento</em>: <em>Administração do site → Extensões → Inscrições → ' .
    'Gerir extensões de inscrição</em>.';
$string['moodle_tip4'] =
    'Adicione-a a uma disciplina: <em>Disciplina → Participantes → Métodos de inscrição → Adicionar método</em>.';
$string['moodle_tips_links'] =
    '<a href="https://docs.moodle.org/501/en/Payment_gateways" target="_blank" rel="noopener">Payment gateways</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Set_up_payment" target="_blank" rel="noopener">Set up payment</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Enrolment_on_payment" target="_blank" rel="noopener">Enrolment on payment</a>';
$string['backoffice_key'] = 'Backoffice Key';
$string['backoffice_key_desc'] = 'Utilizada para autenticar chamadas à API e webhooks.';
$string['methods_showcase_title'] = 'Métodos de pagamento suportados';
$string['status_unconfigured_title'] = 'Ainda não ligado';
$string['status_unconfigured_desc'] =
    'A ifthenpay é um serviço gratuito. Quatro passos para começar a aceitar pagamentos.';
$string['status_connected_title'] = 'Ligado ao ifthenpay';
$string['status_connected_desc'] =
    'A sua Backoffice Key está configurada e o ifthenpay está pronto para aceitar pagamentos. Precisa de ativar outro ' .
    'método de pagamento? <a href="mailto:suporte@ifthenpay.com">Contacte o suporte ifthenpay</a>.';
$string['onboarding_toggle'] = 'Mostrar passos de subscrição';
$string['status_nomoodlekeys_title'] = 'Ainda sem Gateway Key para Moodle';
$string['status_nomoodlekeys_desc'] =
    'A sua Backoffice Key é válida, mas ainda não tem associada nenhuma Gateway Key com contexto Moodle, pelo que não há ' .
    'nada para selecionar no formulário da conta de pagamento. <a href="mailto:suporte@ifthenpay.com">Solicite uma ao ' .
    'suporte ifthenpay</a>, com os métodos de pagamento pretendidos ativados.';
$string['status_rejected_title'] = 'Backoffice Key rejeitada';
$string['status_rejected_desc'] =
    'A ifthenpay não reconheceu a Backoffice Key configurada em baixo, pelo que não é possível processar pagamentos. ' .
    'Verifique-a no seu backoffice ifthenpay ou <a href="mailto:suporte@ifthenpay.com">contacte o suporte ifthenpay</a>.';

// Validation / messages.
$string['error_invalidformat'] = 'Formato inválido. Use 1234-5678-9012-3456.';
$string['error_invalid_backoffice_key'] = 'A Backoffice Key não é válida. Verifique e tente novamente.';
$string['error_missing_backoffice_key'] = 'A Backoffice Key não está configurada. Por favor, configure-a nas definições da gateway.';


// Errors for API responses.
$string['api:nobackofficekey_error'] = 'API: Nenhuma Backoffice Key configurada.';
$string['api:error_invalid_pbl_response'] = 'Resposta inválida da API Pay-by-Link.';
$string['api:error_invalid_json_get'] = 'Resposta JSON inválida no pedido GET: {$a}';
$string['api:error_invalid_json_post'] = 'Resposta JSON inválida no pedido POST.';
$string['api:error_http_request_failed'] = 'Falha na chamada HTTP: {$a}';
$string['api:error_http_status'] = 'Erro HTTP da API: {$a}';
$string['api:error_unauthorized'] = 'A API rejeitou as credenciais: {$a}';


// Form – sections & labels.
$string['form:gateway_key'] = 'Gateway Key';
$string['form:gateway_key_help'] = 'Precisa de outra key? <a href="mailto:suporte@ifthenpay.com">Contacte o suporte ifthenpay</a>. Novas keys e contas surgem automaticamente após ativação.';

$string['form:payment_configuration'] = 'Métodos de pagamento';
$string['form:payment_configuration_reqnote'] = '<strong>Obrigatório:</strong> Ative pelo menos um método de pagamento.';
$string['form:method_not_activated'] = 'Não ativado para esta Gateway Key &mdash; <a href="mailto:suporte@ifthenpay.com">solicite ao suporte ifthenpay</a> que o adicione.';
$string['form:gateway_key_no_methods'] = 'Esta Gateway Key não tem métodos de pagamento suportados por esta extensão, pelo que nenhum pode ser ativado em baixo. Escolha outra Gateway Key ou <a href="mailto:suporte@ifthenpay.com">solicite ao suporte ifthenpay</a> que lhe adicione métodos.';
$string['form:col_method'] = 'Método';
$string['form:col_account'] = 'Conta';
$string['form:col_default'] = 'Predefinido';

$string['form:default_method'] = 'Método predefinido (Opcional)';
$string['form:enable_method'] = 'Ativar {$a}';
$string['form:set_default_method'] = 'Definir {$a} como método predefinido';
$string['form:default_unsupported'] = 'Este método de pagamento não pode ser pré-selecionado no checkout.';
$string['form:default_method_help'] =
    'Opcional. Se ativo, este método será o pré-selecionado no checkout quando multiplicos métodos estão ativos. Selecione "Nenhum" para que o cliente escolha sem a pré-seleção.';
$string['form:default_method_none'] = 'Nenhum';
$string['form:description'] = 'Descrição do checkout (Opcional)';
$string['form:description_help'] = 'Texto opcional, até 150 caracteres, apresentado no checkout.';

$string['form:missing_backoffice_key_inline'] = 'A Backoffice Key não está configurada. <a href="{$a}">Abrir definições</a>.';
$string['form:rejected_backoffice_key_inline'] = 'A ifthenpay não reconheceu a Backoffice Key configurada. <a href="{$a}">Abrir definições</a> para a corrigir.';
$string['form:missing_gateway_keys_inline'] =
    'Não existe nenhuma Gateway Key configurada para o Moodle no seu backoffice da ifthenpay. Por favor, <a href="mailto:suporte@ifthenpay.com">contacte o suporte ifthenpay</a> para criar uma Gateway Key para o Moodle e atribuir os métodos de pagamento que pretende aceitar. Depois de criada, volte aqui e selecione-a.';

// Validation / messages.
$string['form:error_unavailable_enable'] = 'Esta gateway não pode ser ativada enquanto não existir uma Gateway Key para Moodle. Desmarque esta opção para guardar, o que desativa a gateway e mantém a restante configuração.';
$string['form:error_state_missing'] = 'Faltam dados de configuração. Por favor, tente guardar novamente.';
$string['form:error_no_methods_enabled'] = 'Ative pelo menos um método de pagamento.';
$string['form:error_default_not_enabled'] = 'O método predefinido "{$a}" tem de estar ativado nos métodos de pagamento.';
$string['form:error_default_unknown'] = 'O método predefinido selecionado "{$a}" não é reconhecido.';
$string['form:error_maxchars'] = 'Máximo de {$a} caracteres.';
$string['form:error_callback_activation'] = 'Falha ao ativar notificações de pagamento. Verifique a sua Backoffice Key e a conectividade à internet, depois guarde novamente. Erro: {$a}';


// Proccessing => pay page.
$string['process:missing_ifthenpay_state'] = 'Não foram encontrados dados de configuração para a ifthenpay. Por favor, contacte o administrador do site.';
$string['process:error_missing_redirect']  = 'Falta a URL de redirecionamento da ifthenpay. Por favor, contacte o administrador do site.';

// Proccessing => cancel/error page.
$string['process:cancel_title']            = 'Pagamento não concluído';
$string['process:cancel_desc_cancel']      = 'Cancelou o pagamento antes de o concluir. Não foi cobrado qualquer valor.';
$string['process:cancel_desc_error']       = 'Ocorreu um problema ao confirmar o pagamento. Não foi cobrado qualquer valor.';
$string['process:btn_try_again']           = 'Tentar novamente';
$string['process:btn_contact_support']     = 'Contactar o suporte';
$string['process:not_found']               = 'Tentativa de pagamento não encontrada.';

// Processing => return page.
$string['process:return_title']            = 'A confirmar o seu pagamento';
$string['process:waiting_hint']            = 'Normalmente demora alguns segundos.';
$string['process:loading']                 = 'A verificar';
$string['process:waiting_timeout']         = 'Ainda a processar. Pode fechar esta página em segurança — a inscrição fica concluída automaticamente assim que a ifthenpay confirmar o pagamento.';
$string['process:order_reference']         = 'Referência da encomenda';
$string['process:transaction_id']          = 'ID da transação';
$string['process:amount']                  = 'Montante';
$string['process:btn_retry']               = 'Verificar novamente';
$string['process:btn_go_to_courses']       = 'Ir para os meus cursos';


// Events.
$string['event:payment_problem'] = 'Problema no pagamento ifthenpay';


// Privacy strings.
$string['privacy:metadata:ifthenpay_tx'] = 'Registo mínimo de transações para o gateway ifthenpay.';
$string['privacy:metadata:ifthenpay_tx:userid'] = 'ID do utilizador associado à tentativa de transação.';
$string['privacy:metadata:ifthenpay_tx:timecreated'] = 'Momento em que a transação foi criada.';
$string['privacy:metadata:ifthenpay_tx:timemodified'] = 'Momento da última atualização da transação.';
$string['privacy:metadata:ifthenpay_tx:token'] = 'Token aleatório que identifica a tentativa de transação.';
$string['privacy:metadata:ifthenpay_tx:component'] = 'Componente que iniciou o pagamento.';
$string['privacy:metadata:ifthenpay_tx:paymentarea'] = 'Área de pagamento dentro do componente.';
$string['privacy:metadata:ifthenpay_tx:itemid'] = 'Identificador do item na área de pagamento.';
$string['privacy:metadata:ifthenpay_tx:accountid'] = 'Conta ifthenpay mapeada utilizada neste pagamento.';
$string['privacy:metadata:ifthenpay_tx:amount'] = 'Montante do pagamento.';
$string['privacy:metadata:ifthenpay_tx:currency'] = 'Moeda do pagamento.';
$string['privacy:metadata:ifthenpay_tx:redirect_url'] = 'URL de retorno utilizado no fluxo de pagamento.';
$string['privacy:metadata:ifthenpay_tx:transaction_id'] = 'Identificador de transação devolvido pela ifthenpay (se existir).';
$string['privacy:metadata:ifthenpay_tx:paymentid'] = 'Ligação ao registo de pagamento do core.';
$string['privacy:metadata:ifthenpay_tx:state'] = 'Estado atual da transação.';
