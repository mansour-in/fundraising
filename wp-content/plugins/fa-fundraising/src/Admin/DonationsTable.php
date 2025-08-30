<?php
namespace FA\Fundraising\Admin;

if (!defined('ABSPATH')) exit;

// Ensure List Table base is loaded
if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DonationsTable extends \WP_List_Table {

    private array $filters = [];
    private int $total = 0;

    public function __construct(array $filters = []) {
        parent::__construct([
            'singular' => 'donation',
            'plural'   => 'donations',
            'ajax'     => false,
        ]);
        $this->filters = $filters;
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'created_at'  => __('Date','fa-fundraising'),
            'donor_name'  => __('Donor','fa-fundraising'),
            'donor_email' => __('Email','fa-fundraising'),
            'amount'      => __('Amount','fa-fundraising'),
            'type'        => __('Type','fa-fundraising'),
            'status'      => __('Status','fa-fundraising'),
            'cause_id'    => __('Cause','fa-fundraising'),
            'orphan_id'   => __('Orphan','fa-fundraising'),
            'receipt_no'  => __('Receipt No','fa-fundraising'),
            'actions'     => __('Actions','fa-fundraising'),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'created_at' => ['created_at', true],
            'amount'     => ['amount', false],
            'status'     => ['status', false],
            'type'       => ['type', false],
        ];
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="donation_ids[]" value="%d" />', (int)$item['id']);
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'created_at':
                return esc_html( get_date_from_gmt($item['created_at'], 'Y-m-d H:i') );
            case 'donor_name':
                return esc_html($item['donor_name'] ?: '—');
            case 'donor_email':
                return esc_html($item['donor_email'] ?: '—');
            case 'amount':
                return '₹'.number_format((float)$item['amount']).' '.esc_html($item['currency']);
            case 'type':
            case 'status':
                return esc_html($item[$column_name]);
            case 'cause_id':
            case 'orphan_id':
                $id = (int)$item[$column_name];
                if (!$id) return '—';
                $post = get_post($id);
                return $post ? sprintf('<a href="%s" target="_blank">%s</a>',
                        esc_url(get_edit_post_link($id)), esc_html(get_the_title($id))) : $id;
            case 'receipt_no':
                return esc_html($item['receipt_no'] ?: '—');
            case 'actions':
                $id = (int)$item['id'];
                $nonce = wp_create_nonce('fa_resend_'.$id);
                $dl_basic = esc_url( rest_url('faf/v1/receipt/'.$id.'?type=basic') );
                $dl_80g   = esc_url( rest_url('faf/v1/receipt/'.$id.'?type=80g') );
                $resend   = esc_url( admin_url('admin-post.php?action=fa_resend_receipt&donation_id='.$id.'&_wpnonce='.$nonce) );
                $links = [];
                if ($item['status']==='captured') {
                    $links[] = '<a href="'.$dl_basic.'" target="_blank">'.__('Download Basic','fa-fundraising').'</a>';
                    $links[] = '<a href="'.$dl_80g.'" target="_blank">'.__('Download 80G','fa-fundraising').'</a>';
                    $links[] = '<a href="'.$resend.'">'.__('Resend Receipt','fa-fundraising').'</a>';
                } else {
                    $links[] = __('—','fa-fundraising');
                }
                return implode(' | ', $links);
            default:
                return isset($item[$column_name]) ? esc_html((string)$item[$column_name]) : '';
        }
    }

    public function no_items() {
        _e('No donations found. Adjust filters and try again.','fa-fundraising');
    }

    public function get_bulk_actions() {
        return [
            'bulk_resend' => __('Resend Receipt (captured only)','fa-fundraising'),
            'bulk_export' => __('Export to CSV','fa-fundraising'),
        ];
    }

    public function prepare_items() {
        $per_page = $this->get_items_per_page('fa_donations_per_page', 20);
        $current_page = $this->get_paged();
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order   = (isset($_GET['order']) && strtolower($_GET['order'])==='asc') ? 'ASC' : 'DESC';

        global $wpdb;
        $don = $wpdb->prefix . 'fa_donations';

        // Build WHERE from filters
        $where = 'WHERE 1=1';
        $args  = [];

        if (!empty($this->filters['s'])) {
            $s = '%'.$wpdb->esc_like($this->filters['s']).'%';
            $where .= " AND (donor_email LIKE %s OR donor_name LIKE %s OR razorpay_payment_id LIKE %s OR receipt_no LIKE %s)";
            array_push($args, $s, $s, $s, $s);
        }
        if (!empty($this->filters['status'])) {
            $where .= " AND status=%s"; $args[] = $this->filters['status'];
        }
        if (!empty($this->filters['type'])) {
            $where .= " AND type=%s"; $args[] = $this->filters['type'];
        }
        if (!empty($this->filters['cause_id'])) {
            $where .= " AND cause_id=%d"; $args[] = (int)$this->filters['cause_id'];
        }
        if (!empty($this->filters['orphan_id'])) {
            $where .= " AND orphan_id=%d"; $args[] = (int)$this->filters['orphan_id'];
        }
        if (!empty($this->filters['from'])) {
            $where .= " AND created_at >= %s"; $args[] = $this->filters['from'].' 00:00:00';
        }
        if (!empty($this->filters['to'])) {
            $where .= " AND created_at <= %s"; $args[] = $this->filters['to'].' 23:59:59';
        }

        $allowed_orderby = ['created_at','amount','status','type','receipt_no'];
        if (!in_array($orderby, $allowed_orderby, true)) $orderby = 'created_at';

        // Count
        $sql_count = "SELECT COUNT(*) FROM $don $where";
        $this->total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $args));

        // Items
        $offset = ($current_page - 1) * $per_page;
        $sql = "SELECT id, created_at, donor_name, donor_email, amount, currency, type, status, cause_id, orphan_id, receipt_no
                FROM $don $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $q_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $q_args), ARRAY_A);

        $this->items = $rows;
        $this->set_pagination_args([
            'total_items' => $this->total,
            'per_page'    => $per_page,
            'total_pages' => ceil($this->total / $per_page),
        ]);
    }

    private function get_paged(): int {
        return max(1, (int)($_GET['paged'] ?? $_GET['page_no'] ?? 1));
    }
}
