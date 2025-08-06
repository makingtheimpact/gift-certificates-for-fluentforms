<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__('Edit Gift Certificate', 'gift-certificates-fluentforms') : esc_html__('Add Gift Certificate', 'gift-certificates-fluentforms'); ?></h1>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('gcff_save_certificate'); ?>
        <input type="hidden" name="action" value="gcff_save_certificate">
        <input type="hidden" name="certificate_id" value="<?php echo esc_attr($certificate->id ?? 0); ?>">
        <table class="form-table">
            <tr>
                <th><label for="coupon_code"><?php _e('Coupon Code', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="coupon_code" type="text" id="coupon_code" value="<?php echo esc_attr($certificate->coupon_code ?? ''); ?>" class="regular-text" maxlength="10"></td>
            </tr>
            <tr>
                <th><label for="recipient_name"><?php _e('Recipient Name', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="recipient_name" type="text" id="recipient_name" value="<?php echo esc_attr($certificate->recipient_name ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="recipient_email"><?php _e('Recipient Email', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="recipient_email" type="email" id="recipient_email" value="<?php echo esc_attr($certificate->recipient_email ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="sender_name"><?php _e('Sender Name', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="sender_name" type="text" id="sender_name" value="<?php echo esc_attr($certificate->sender_name ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="original_amount"><?php _e('Original Amount', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="original_amount" type="number" step="0.01" id="original_amount" value="<?php echo esc_attr($certificate->original_amount ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="current_balance"><?php _e('Current Balance', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="current_balance" type="number" step="0.01" id="current_balance" value="<?php echo esc_attr($certificate->current_balance ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="delivery_date"><?php _e('Delivery Date', 'gift-certificates-fluentforms'); ?></label></th>
                <td><input name="delivery_date" type="date" id="delivery_date" value="<?php echo esc_attr($certificate->delivery_date ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="design_id"><?php _e('Design', 'gift-certificates-fluentforms'); ?></label></th>
                <td>
                    <select name="design_id" id="design_id">
                        <?php foreach ($designs as $id => $design): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($certificate->design_id ?? 'default', $id); ?>><?php echo esc_html($design['name'] ?? $id); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="status"><?php _e('Status', 'gift-certificates-fluentforms'); ?></label></th>
                <td>
                    <select name="status" id="status">
                        <?php
                        $statuses = array(
                            'active' => __('Active', 'gift-certificates-fluentforms'),
                            'expired' => __('Expired', 'gift-certificates-fluentforms'),
                            'pending_delivery' => __('Pending Delivery', 'gift-certificates-fluentforms'),
                            'delivered' => __('Delivered', 'gift-certificates-fluentforms'),
                        );
                        $current_status = $certificate->status ?? 'active';
                        foreach ($statuses as $key => $label) {
                            echo '<option value="' . esc_attr($key) . '" ' . selected($current_status, $key, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="message"><?php _e('Message', 'gift-certificates-fluentforms'); ?></label></th>
                <td><textarea name="message" id="message" rows="5" class="large-text"><?php echo esc_textarea($certificate->message ?? ''); ?></textarea></td>
            </tr>
        </table>
        <?php submit_button($is_edit ? __('Update Certificate', 'gift-certificates-fluentforms') : __('Create Certificate', 'gift-certificates-fluentforms')); ?>
    </form>
 </div>

