<?php
namespace FA\Fundraising\Admin;

use FA\Fundraising\Service\EmailService;

if (!defined('ABSPATH')) exit;

class DonationsAdmin {

    public function init(): void {
        add_action('admin_menu', [$this,'menu']);
        add_filter('set-screen-option', [$this,'save_per_page'], 10, 3);

        add_action('admin_post_fa_export_donations', [$this,'handle_export']);
        add_action('admin_post_fa_resend_receipt',  [$this,'handle_resend']);

        // Screen option (per page)
        add_action('load-fa-fundraising_page_fa-donations', [$this,'add_screen_options']);
    }

    public function menu(): void {
        // Add as submenu under FA Fundraising main
        add_submenu_page(
            'fa-fundraising',
            __('Donations','fa-fundraising'),
            __('Donations','fa-fundraising'),
            'manage_options',
            'fa-donations',
            [$this,'render']
        );
    }

    public function add_screen_options(): void {
        add_screen_option('per_page', [
            'label'   => __('Donations per page','fa-fundraising'),
            'default' => 20,
            'option'  => 'fa_donations_per_page'
        ]);
    }
    public function save_per_page($status, $option, $value) {
        if ('fa_donations_per_page' === $option) return (int)$value;
        return $status;
    }

    private function current_filters(): array {
        return [
            's'         => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'status'    => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'type'      => isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '',
            'cause_id'  => isset($_GET['cause_id']) ? (int)$_GET['cause_id'] : 0,
            'orphan_id' => isset($_GET['orphan_id']) ? (int)$_GET['orphan_id'] : 0,
            'from'      => isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '',
            'to'        => isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '',
        ];
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.','fa-fundraising'));
        }
        $filters = $this->current_filters();
        $table = new DonationsTable($filters);
        $table->prepare_items();

        $export_url = wp_nonce_url(admin_url('admin-post.php?action=fa_export_donations&'.http_build_query($filters)), 'fa_export_csv');
        if (isset($_GET['msg'])) {
            echo '<div class="notice notice-success"><p>';
            if ($_GET['msg']==='resent') echo esc_html__('Receipt email has been resent.','fa-fundraising');
            if ($_GET['msg']==='only-captured') echo esc_html__('Only captured payments can receive receipts.','fa-fundraising');
            echo '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Donations','fa-fundraising'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="page-title-action"><?php esc_html_e('Export CSV (current filters)','fa-fundraising'); ?></a>
            <hr class="wp-header-end">

            <form method="get" style="margin-bottom:8px;">
                <input type="hidden" name="page" value="fa-donations">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="search" name="s" value="<?php echo esc_attr($filters['s']); ?>" placeholder="<?php esc_attr_e('Search email, name, payment id, receipt no','fa-fundraising'); ?>" class="regular-text">
                    <select name="status">
                        <option value=""><?php esc_html_e('All Status','fa-fundraising'); ?></option>
                        <?php foreach (['captured','pending','failed'] as $st): ?>
                            <option value="<?php echo esc_attr($st); ?>" <?php selected($filters['status'],$st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type">
                        <option value=""><?php esc_html_e('All Types','fa-fundraising'); ?></option>
                        <?php foreach (['general','cause','sponsorship'] as $tp): ?>
                            <option value="<?php echo esc_attr($tp); ?>" <?php selected($filters['type'],$tp); ?>><?php echo esc_html(ucfirst($tp)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cause_id" value="<?php echo esc_attr($filters['cause_id'] ?: ''); ?>" placeholder="<?php esc_attr_e('Cause ID','fa-fundraising'); ?>" class="small-text">
                    <input type="number" name="orphan_id" value="<?php echo esc_attr($filters['orphan_id'] ?: ''); ?>" placeholder="<?php esc_attr_e('Orphan ID','fa-fundraising'); ?>" class="small-text">
                    <input type="date" name="from" value="<?php echo esc_attr($filters['from']); ?>">
                    <input type="date" name="to"   value="<?php echo esc_attr($filters['to']); ?>">
                    <button class="button"><?php esc_html_e('Filter','fa-fundraising'); ?></button>
                </div>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php
                    $table->display();
                ?>
                <input type="hidden" name="action" value="fa_bulk_actions">
            </form>
        </div>
        <?php
    }

    /** CSV export for current filters */
    public function handle_export(): void {
        if (!current_user_can('manage_options')) wp_die('forbidden');
        check_admin_referer('fa_export_csv');

        $filters = [
            's'         => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'status'    => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'type'      => isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '',
            'cause_id'  => isset($_GET['cause_id']) ? (int)$_GET['cause_id'] : 0,
            'orphan_id' => isset($_GET['orphan_id']) ? (int)$_GET['orphan_id'] : 0,
            'from'      => isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '',
            'to'        => isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '',
        ];

        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';
        $where = 'WHERE 1=1'; $args = [];
        if ($filters['s']!==''){
            $s = '%'.$wpdb->esc_like($filters['s']).'%';
            $where .= " AND (donor_email LIKE %s OR donor_name LIKE %s OR razorpay_payment_id LIKE %s OR receipt_no LIKE %s)";
            array_push($args,$s,$s,$s,$s);
        }
        if ($filters['status'])   { $where .= " AND status=%s"; $args[]=$filters['status']; }
        if ($filters['type'])     { $where .= " AND type=%s";   $args[]=$filters['type']; }
        if ($filters['cause_id']) { $where .= " AND cause_id=%d";$args[]=$filters['cause_id']; }
        if ($filters['orphan_id']){ $where .= " AND orphan_id=%d";$args[]=$filters['orphan_id']; }
        if ($filters['from'])     { $where .= " AND created_at >= %s"; $args[]=$filters['from'].' 00:00:00'; }
        if ($filters['to'])       { $where .= " AND created_at <= %s"; $args[]=$filters['to'].' 23:59:59'; }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT created_at, donor_name, donor_email, amount, currency, type, status, cause_id, orphan_id, razorpay_payment_id, receipt_no
             FROM $don $where ORDER BY created_at DESC LIMIT 50000", $args
        ), ARRAY_A);

        // Output CSV
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="donations-export-'.date('Ymd-His').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date','Donor','Email','Amount','Currency','Type','Status','Cause ID','Orphan ID','Payment ID','Receipt No']);
        foreach ($rows as $r) {
            fputcsv($out, [
                get_date_from_gmt($r['created_at'], 'Y-m-d H:i'),
                $r['donor_name'], $r['donor_email'], $r['amount'], $r['currency'],
                $r['type'], $r['status'], $r['cause_id'], $r['orphan_id'], $r['razorpay_payment_id'], $r['receipt_no']
            ]);
        }
        fclose($out);
        exit;
    }

    /** Single row resend */
    public function handle_resend(): void {
        if (!current_user_can('manage_options')) wp_die('forbidden');
        $id = (int)($_GET['donation_id'] ?? 0);
        if (!$id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'fa_resend_'.$id)) wp_die('bad nonce');

        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $don WHERE id=%d", $id), ARRAY_A);
        if (!$row) wp_die('not found');

        if ($row['status'] !== 'captured') {
            wp_redirect( add_query_arg(['page'=>'fa-donations','msg'=>'only-captured'], admin_url('admin.php')) );
            exit;
        }

        EmailService::send_thankyou_with_receipt($row);
        wp_redirect( add_query_arg(['page'=>'fa-donations','msg'=>'resent'], admin_url('admin.php')) );
        exit;
    }
}
