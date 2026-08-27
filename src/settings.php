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
 * Settings for the ifthenpay payment gateway.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

if ($ADMIN->fulltree) {
    // Three sections: the informational card, then the connection setting, then behaviour. This
    // file decides only what to say; the markup lives in paygw_ifthenpay/settings_status, whose
    // template documentation explains why nothing informational is interleaved with the settings.
    $keystatus = paygw_ifthenpay_backoffice_key_status();

    // Numbered instructions, built from prose-only strings (see the steps template).
    $stepsfor = function (string $prefix, int $count, string $badgeclass, string $footerkey = ''): array {
        $steps = [];
        for ($i = 1; $i <= $count; $i++) {
            $steps[] = ['number' => $i, 'text' => get_string($prefix . $i, 'paygw_ifthenpay')];
        }
        $context = ['badgeclass' => $badgeclass, 'steps' => $steps];
        if ($footerkey !== '') {
            $context['footer'] = get_string($footerkey, 'paygw_ifthenpay');
        }
        return $context;
    };

    // Branded badges for ifthenpay's own steps; Moodle's setup steps are deliberately quieter.
    // Foreground is stated with background: Boost's $secondary is a light grey, so text-white on
    // it measures 1.49:1, well under the 4.5:1 minimum.
    $subscriptionsteps = $stepsfor('onboarding_step', 4, 'bg-primary text-white', 'onboarding_more_info');
    $moodlesteps = $stepsfor('moodle_tip', 4, 'bg-secondary text-dark', 'moodle_tips_links');

    /*
     * Only a problem gets an alert. "Connected" is a steady state and "not connected yet" is the
     * expected first run, so both read as a plain heading; a rejected or unusable key is coloured
     * as the problem it is. String keys are written out rather than built from $keystatus so that
     * grepping for one still finds its use.
     */
    $states = [
        'unconfigured' => ['icon' => 'i/info', 'iconclass' => 'text-primary', 'alert' => ''],
        'rejected'     => ['icon' => 'i/invalid', 'iconclass' => '', 'alert' => 'danger'],
        'nomoodlekeys' => ['icon' => 'i/warning', 'iconclass' => '', 'alert' => 'warning'],
        'ok'           => ['icon' => 'i/valid', 'iconclass' => 'text-success', 'alert' => ''],
    ];
    $state = $states[$keystatus] ?? $states['ok'];

    $titles = [
        'unconfigured' => ['status_unconfigured_title', 'status_unconfigured_desc'],
        'rejected'     => ['status_rejected_title', 'status_rejected_desc'],
        'nomoodlekeys' => ['status_nomoodlekeys_title', 'status_nomoodlekeys_desc'],
        'ok'           => ['status_connected_title', 'status_connected_desc'],
    ];
    [$titlekey, $desckey] = $titles[$keystatus] ?? $titles['ok'];

    /*
     * ifthenpay's public catalog (/gateway/methods/available — keyless, so it renders before an
     * account is connected). What ifthenpay offers in general, not what this client has activated:
     * that is only known per Gateway Key, on the payment account form. Fails soft to nothing when
     * the API is unreachable.
     */
    $methods = [];
    foreach (paygw_ifthenpay_get_methods_rich() as $methodkey => $meta) {
        if (empty($meta['image'])) {
            continue;
        }
        $methods[] = [
            'label' => $meta['label'] ?? $methodkey,
            'image' => $meta['image'],
        ];
    }

    // On first run the subscription steps are the whole point of the page, so they are shown open
    // in the card body and not repeated here.
    $disclosures = [];
    if ($keystatus !== 'unconfigured') {
        $disclosures[] = [
            'id' => 'ifp-subscription-steps',
            'label' => get_string('onboarding_toggle', 'paygw_ifthenpay'),
            'steps' => $subscriptionsteps,
        ];
    }
    $disclosures[] = [
        'id' => 'ifp-moodle-tips',
        'label' => get_string('moodle_payment_tips_title', 'paygw_ifthenpay'),
        'steps' => $moodlesteps,
    ];

    // Core's .icons-collapse-expand shows exactly one of these, depending on Bootstrap's
    // .collapsed class.
    $expandedicon = $OUTPUT->pix_icon('t/expanded', '', 'core');
    $collapsedicon = $OUTPUT->pix_icon('t/collapsed', '', 'core');
    foreach ($disclosures as $i => $disclosure) {
        $disclosures[$i]['expandedicon'] = $expandedicon;
        $disclosures[$i]['collapsedicon'] = $collapsedicon;
    }

    $card = $OUTPUT->render_from_template('paygw_ifthenpay/settings_status', [
        'icon' => $OUTPUT->pix_icon($state['icon'], '', 'core', ['class' => 'icon ' . $state['iconclass']]),
        'alertlevel' => $state['alert'],
        'title' => get_string($titlekey, 'paygw_ifthenpay'),
        'description' => get_string($desckey, 'paygw_ifthenpay'),
        'steps' => $keystatus === 'unconfigured' ? $subscriptionsteps : null,
        'methodstitle' => get_string('methods_showcase_title', 'paygw_ifthenpay'),
        'methods' => $methods,
        'disclosures' => $disclosures,
    ]);

    // No heading text: the page is already titled "ifthenpay" and the card states its own
    // subject, so a section title here would repeat one of them.
    $settings->add(new \admin_setting_heading('paygw_ifthenpay/onboarding', '', $card));

    $settings->add(new \admin_setting_heading(
        'paygw_ifthenpay/api_heading',
        get_string('api_heading', 'paygw_ifthenpay'),
        ''
    ));

    $settings->add(new \paygw_ifthenpay\adminsetting\backofficekey(
        'paygw_ifthenpay/backoffice_key',
        get_string('backoffice_key', 'paygw_ifthenpay'),
        get_string('backoffice_key_desc', 'paygw_ifthenpay'),
        ''
    ));

    $settings->add(new \admin_setting_heading(
        'paygw_ifthenpay/behavior_heading',
        get_string('behavior_heading', 'paygw_ifthenpay'),
        get_string('behavior_desc', 'paygw_ifthenpay')
    ));

    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_ifthenpay');
}
