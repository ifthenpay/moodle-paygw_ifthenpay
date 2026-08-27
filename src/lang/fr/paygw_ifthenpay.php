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
 * Strings for component 'paygw_ifthenpay', language 'fr'
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Default.
$string['pluginname'] = 'ifthenpay';
// Voir la note dans la version anglaise : le logo identifie déjà la marque, la description indique
// donc uniquement les moyens de paiement disponibles.
$string['gatewayname'] = 'ifthenpay';
$string['gatewaydescription'] =
    '<span class="d-block mb-1">Payez en toute sécurité par carte, Apple Pay, Google Pay, MB WAY, Multibanco, ' .
    'Payshop ou Pix.</span>' .
    '<span class="d-block small text-muted">Les moyens disponibles dépendent de ceux que votre prestataire a ' .
    'activés.</span>';


// Modal (moustache).
$string['modal:redirectingtoifthenpay'] = 'Redirection vers ifthenpay pour finaliser votre paiement…';
// Settings / headings.
$string['api_heading'] = 'Connectez votre compte ifthenpay';
$string['behavior_heading'] = 'Comportement du paiement';
$string['behavior_desc'] = 'Réglages facultatifs affectant la façon dont cette passerelle est présentée aux utilisateurs.';
// Onboarding et conseils. La structure (liste, numérotation, badges) réside dans le gabarit steps —
// ces chaînes ne contiennent que du texte, afin qu'un traducteur n'ait jamais à conserver de classes Bootstrap.
$string['onboarding_step1'] =
    '<a href="https://ifthenpay.com/aderir/" target="_blank" rel="noopener">Souscrivez et signez le contrat</a>, ' .
    'en sélectionnant les moyens de paiement que vous souhaitez accepter.';
$string['onboarding_step2'] =
    'Une fois le contrat conclu, vous recevrez automatiquement une Backoffice Key — saisissez-la ci-dessous.';
$string['onboarding_step3'] =
    'Demandez au <a href="mailto:suporte@ifthenpay.com">support ifthenpay</a> une Gateway Key ' .
    '<strong>pour Moodle</strong>, avec les moyens de paiement choisis activés.';
$string['onboarding_step4'] = 'Tout le reste se configure ici, dans Moodle.';
$string['onboarding_more_info'] =
    'Déjà sous contrat ? Il suffit de demander la Gateway Key. Plus d\'informations sur ' .
    '<a href="https://ifthenpay.com" target="_blank" rel="noopener">ifthenpay.com</a>.';
$string['moodle_payment_tips_title'] = 'Nouveau dans les paiements Moodle ?';
$string['moodle_tip1'] =
    'Créez un compte de paiement : <em>Administration du site → Paiements → Comptes de paiement → ' .
    'Créer un compte de paiement</em>.';
$string['moodle_tip2'] = 'Activez la passerelle ifthenpay sur ce compte (ci-dessous).';
$string['moodle_tip3'] =
    'Activez l\'<em>Inscription par paiement</em> : <em>Administration du site → Plugins → Inscriptions → ' .
    'Gestion des plugins d\'inscription</em>.';
$string['moodle_tip4'] =
    'Ajoutez-la à un cours : <em>Cours → Participants → Méthodes d\'inscription → Ajouter une méthode</em>.';
$string['moodle_tips_links'] =
    '<a href="https://docs.moodle.org/501/en/Payment_gateways" target="_blank" rel="noopener">Payment gateways</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Set_up_payment" target="_blank" rel="noopener">Set up payment</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Enrolment_on_payment" target="_blank" rel="noopener">Enrolment on payment</a>';
$string['backoffice_key'] = 'Backoffice Key';
$string['backoffice_key_desc'] = 'Utilisée pour authentifier les appels à l\'API et les webhooks.';
$string['methods_showcase_title'] = 'Moyens de paiement pris en charge';
$string['status_unconfigured_title'] = 'Pas encore connecté';
$string['status_unconfigured_desc'] =
    'ifthenpay est un service gratuit. Quatre étapes pour commencer à accepter des paiements.';
$string['status_connected_title'] = 'Connecté à ifthenpay';
$string['status_connected_desc'] =
    'Votre Backoffice Key est configurée et ifthenpay est prêt à accepter des paiements. ' .
    'Besoin d\'activer un autre moyen de paiement ? ' .
    '<a href="mailto:suporte@ifthenpay.com">Contactez le support ifthenpay</a>.';
$string['onboarding_toggle'] = 'Afficher les étapes de souscription';
$string['status_nomoodlekeys_title'] = 'Aucune Gateway Key pour Moodle pour le moment';
$string['status_nomoodlekeys_desc'] =
    'Votre Backoffice Key est valide, mais aucune Gateway Key au contexte Moodle ne lui est encore attribuée : il n\'y ' .
    'a donc rien à sélectionner dans le formulaire du compte de paiement. ' .
    '<a href="mailto:suporte@ifthenpay.com">Demandez-en une au support ifthenpay</a>, avec les moyens de paiement ' .
    'choisis activés.';
$string['status_rejected_title'] = 'Backoffice Key refusée';
$string['status_rejected_desc'] =
    'ifthenpay n\'a pas reconnu la Backoffice Key configurée ci-dessous, les paiements ne peuvent donc pas être ' .
    'traités. Vérifiez-la dans votre backoffice ifthenpay ou ' .
    '<a href="mailto:suporte@ifthenpay.com">contactez le support ifthenpay</a>.';

// Validation / messages.
$string['error_invalidformat'] = 'Format non valide. Utilisez 1234-5678-9012-3456.';
$string['error_invalid_backoffice_key'] = 'La Backoffice Key n\'est pas valide. Veuillez la vérifier et réessayer.';
$string['error_missing_backoffice_key'] =
    'La Backoffice Key n\'est pas configurée. Veuillez la renseigner dans les réglages de la passerelle.';


// Errors for API responses.
$string['api:nobackofficekey_error'] = 'API : aucune Backoffice Key configurée.';
$string['api:error_invalid_pbl_response'] = 'Réponse non valide de l\'API Pay-by-Link.';
$string['api:error_invalid_json_get'] = 'JSON non valide sur la requête GET : {$a}';
$string['api:error_invalid_json_post'] = 'JSON non valide sur la requête POST.';
$string['api:error_http_request_failed'] = 'Échec de la requête HTTP : {$a}';
$string['api:error_http_status'] = 'Erreur HTTP de l\'API : {$a}';
$string['api:error_unauthorized'] = 'L\'API a refusé les identifiants : {$a}';


// Form – sections & labels.
$string['form:gateway_key'] = 'Gateway Key';
$string['form:gateway_key_help'] =
    'Besoin d\'une autre key ? <a href="mailto:suporte@ifthenpay.com">Contactez le support ifthenpay</a>. ' .
    'Les nouvelles keys et comptes apparaissent automatiquement après activation.';

$string['form:payment_configuration'] = 'Moyens de paiement';
$string['form:payment_configuration_reqnote'] =
    '<strong>Obligatoire :</strong> veuillez activer au moins un moyen de paiement.';
$string['form:method_not_activated'] =
    'Non activé pour cette Gateway Key &mdash; <a href="mailto:suporte@ifthenpay.com">demandez au support ' .
    'ifthenpay</a> de l\'ajouter.';
$string['form:gateway_key_no_methods'] =
    'Cette Gateway Key ne comporte aucun moyen de paiement pris en charge par ce plugin : aucun ne peut donc être ' .
    'activé ci-dessous. Choisissez une autre Gateway Key ou ' .
    '<a href="mailto:suporte@ifthenpay.com">demandez au support ifthenpay</a> d\'y ajouter des moyens de paiement.';
$string['form:col_method'] = 'Moyen';
$string['form:col_account'] = 'Compte';
$string['form:col_default'] = 'Par défaut';

$string['form:default_method'] = 'Moyen par défaut (facultatif)';
$string['form:enable_method'] = 'Activer {$a}';
$string['form:set_default_method'] = 'Définir {$a} comme moyen par défaut';
$string['form:default_unsupported'] = 'Ce moyen de paiement ne peut pas être présélectionné au checkout.';
$string['form:default_method_help'] =
    'Facultatif. S\'il est défini, ce moyen est présélectionné au checkout lorsque plusieurs moyens sont activés. ' .
    'Laissez « Aucun » pour que le client choisisse sans présélection.';
$string['form:default_method_none'] = 'Aucun';
$string['form:description'] = 'Description du checkout (facultatif)';
$string['form:description_help'] = 'Texte facultatif, jusqu\'à 150 caractères, affiché au checkout.';

$string['form:missing_backoffice_key_inline'] =
    'La Backoffice Key n\'est pas configurée. <a href="{$a}">Ouvrir les réglages</a>.';
$string['form:rejected_backoffice_key_inline'] =
    'ifthenpay n\'a pas reconnu la Backoffice Key configurée. <a href="{$a}">Ouvrez les réglages</a> pour la corriger.';
$string['form:missing_gateway_keys_inline'] =
    'Aucune Gateway Key n\'est configurée pour Moodle dans votre backoffice ifthenpay. ' .
    '<a href="mailto:suporte@ifthenpay.com">Contactez le support ifthenpay</a> pour créer une Gateway Key pour Moodle ' .
    'et lui attribuer les moyens de paiement que vous comptez accepter. Une fois créée, revenez ici et ' .
    'sélectionnez-la.';

// Validation / messages.
$string['form:error_unavailable_enable'] =
    'Cette passerelle ne peut pas être activée tant qu\'aucune Gateway Key pour Moodle n\'est disponible. Décochez ' .
    'cette case pour enregistrer : la passerelle est désactivée et le reste de sa configuration est conservé.';
$string['form:error_state_missing'] =
    'Les données de configuration sont absentes. Veuillez réessayer d\'enregistrer.';
$string['form:error_no_methods_enabled'] = 'Veuillez activer au moins un moyen de paiement.';
$string['form:error_default_not_enabled'] =
    'Le moyen par défaut « {$a} » doit être activé dans Moyens de paiement.';
$string['form:error_default_unknown'] = 'Le moyen par défaut sélectionné « {$a} » n\'est pas reconnu.';
$string['form:error_maxchars'] = 'Maximum {$a} caractères.';
$string['form:error_callback_activation'] =
    'Échec de l\'activation des notifications de paiement. Vérifiez votre Backoffice Key et votre connexion internet, ' .
    'puis enregistrez à nouveau. Erreur : {$a}';


// Proccessing => pay page.
$string['process:missing_ifthenpay_state'] =
    'Aucune donnée de configuration trouvée pour ifthenpay. Veuillez contacter l\'administrateur du site.';
$string['process:error_missing_redirect']  =
    'URL de redirection manquante de la part d\'ifthenpay. Veuillez contacter l\'administrateur du site.';

// Proccessing => cancel/error page.
$string['process:cancel_title']            = 'Paiement non finalisé';
$string['process:cancel_desc_cancel']      = 'Vous avez annulé le paiement avant sa finalisation. Rien n\'a été débité.';
$string['process:cancel_desc_error']       =
    'Une erreur s\'est produite lors de la confirmation de votre paiement. Rien n\'a été débité.';
$string['process:btn_try_again']           = 'Réessayer';
$string['process:btn_contact_support']     = 'Contacter le support';
$string['process:not_found']               = 'Tentative de paiement introuvable.';

// Processing => return page.
$string['process:return_title']            = 'Confirmation de votre paiement';
$string['process:waiting_hint']            = 'Cela prend généralement quelques secondes.';
$string['process:loading']                 = 'Vérification';
$string['process:waiting_timeout']         =
    'Traitement toujours en cours. Vous pouvez fermer cette page en toute sécurité : votre inscription se finalise ' .
    'automatiquement dès qu\'ifthenpay confirme le paiement.';
$string['process:order_reference']         = 'Référence de la commande';
$string['process:transaction_id']          = 'ID de transaction';
$string['process:amount']                  = 'Montant';
$string['process:btn_retry']               = 'Vérifier à nouveau';
$string['process:btn_go_to_courses']       = 'Aller à Mes cours';


// Events.
$string['event:payment_problem'] = 'Problème de paiement ifthenpay';


// Privacy strings.
$string['privacy:metadata:ifthenpay_tx'] = 'Suivi minimal des transactions pour la passerelle ifthenpay.';
$string['privacy:metadata:ifthenpay_tx:userid'] = 'ID de l\'utilisateur associé à la tentative de transaction.';
$string['privacy:metadata:ifthenpay_tx:timecreated'] = 'Date de création de la transaction.';
$string['privacy:metadata:ifthenpay_tx:timemodified'] = 'Date de dernière mise à jour de la transaction.';
$string['privacy:metadata:ifthenpay_tx:token'] = 'Jeton aléatoire identifiant la tentative de transaction.';
$string['privacy:metadata:ifthenpay_tx:component'] = 'Composant à l\'origine du paiement.';
$string['privacy:metadata:ifthenpay_tx:paymentarea'] = 'Zone de paiement au sein du composant.';
$string['privacy:metadata:ifthenpay_tx:itemid'] = 'Identifiant de l\'élément dans la zone de paiement.';
$string['privacy:metadata:ifthenpay_tx:accountid'] = 'Compte ifthenpay utilisé pour ce paiement.';
$string['privacy:metadata:ifthenpay_tx:amount'] = 'Montant du paiement.';
$string['privacy:metadata:ifthenpay_tx:currency'] = 'Devise du paiement.';
$string['privacy:metadata:ifthenpay_tx:redirect_url'] = 'URL de retour utilisée durant le flux de paiement.';
$string['privacy:metadata:ifthenpay_tx:transaction_id'] =
    'Identifiant de transaction renvoyé par ifthenpay (si disponible).';
$string['privacy:metadata:ifthenpay_tx:paymentid'] = 'Lien vers l\'enregistrement de paiement du noyau.';
$string['privacy:metadata:ifthenpay_tx:state'] = 'État actuel de la transaction.';
