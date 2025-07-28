<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('All Gift Certificates', 'gift-certificates-fluentforms'); ?></h1>
    
    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="gift-certificates-ff-list">
                
                <select name="status">
                    <option value=""><?php _e('All Statuses', 'gift-certificates-fluentforms'); ?></option>
                    <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'gift-certificates-fluentforms'); ?></option>
                    <option value="expired" <?php selected($status, 'expired'); ?>><?php _e('Expired', 'gift-certificates-fluentforms'); ?></option>
                    <option value="pending_delivery" <?php selected($status, 'pending_delivery'); ?>><?php _e('Pending Delivery', 'gift-certificates-fluentforms'); ?></option>
                    <option value="delivered" <?php selected($status, 'delivered'); ?>><?php _e('Delivered', 'gift-certificates-fluentforms'); ?></option>
                </select>
                
                <input type="submit" class="button" value="<?php _e('Filter', 'gift-certificates-fluentforms'); ?>">
            </form>
        </div>
        
        <div class="alignright">
            <a href="<?php echo admin_url('admin.php?page=gift-certificates-ff'); ?>" class="button button-primary">
                <?php _e('Back to Dashboard', 'gift-certificates-fluentforms'); ?>
            </a>
        </div>
    </div>
    
    <!-- Certificates Table -->
    <?php if (!empty($certificates)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Code', 'gift-certificates-fluentforms'); ?></th>
                    <th><?php _e('Recipient', 'gift-certificates-fluentforms'); ?></th>
                    <th><?php _e('Amount', 'gift-certificates-fluentforms'); ?></th>
                    <th><?php _e('Balance', 'gift-certificates-fluentforms'); ?></th>
                    <th><?php _e('Status', 'gift-certificates-fluentforms'); ?></th>
                    <th><?php _e('Created', 'gift-certificates-fluentforms'); ?></th>
                    <th><?php _e('Actions', 'gift-certificates-fluentforms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificates as $certificate): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($certificate->coupon_code); ?></strong>
                            <div class="row-actions">
                                <span class="copy-code" data-code="<?php echo esc_attr($certificate->coupon_code); ?>">
                                    <a href="#" onclick="copyToClipboard('<?php echo esc_js($certificate->coupon_code); ?>'); return false;">
                                        <?php _e('Copy Code', 'gift-certificates-fluentforms'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php echo esc_html($certificate->recipient_name); ?><br>
                            <small><?php echo esc_html($certificate->recipient_email); ?></small>
                        </td>
                        <td>$<?php echo number_format($certificate->original_amount, 2); ?></td>
                        <td>$<?php echo number_format($certificate->current_balance, 2); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($certificate->status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $certificate->status))); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($certificate->created_at)); ?></td>
                        <td>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo admin_url('admin.php?page=gift-certificates-ff-list&action=view&id=' . $certificate->id); ?>">
                                        <?php _e('View', 'gift-certificates-fluentforms'); ?>
                                    </a> |
                                </span>
                                <span class="resend">
                                    <a href="#" class="resend-certificate" data-id="<?php echo $certificate->id; ?>">
                                        <?php _e('Resend', 'gift-certificates-fluentforms'); ?>
                                    </a> |
                                </span>
                                <span class="delete">
                                    <a href="#" class="delete-certificate" data-id="<?php echo $certificate->id; ?>" style="color: #a00;">
                                        <?php _e('Delete', 'gift-certificates-fluentforms'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total > 20): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $total_pages = ceil($total / 20);
                    $current_page = $page;
                    
                    if ($total_pages > 1) {
                        echo '<span class="pagination-links">';
                        
                        // Previous page
                        if ($current_page > 1) {
                            echo '<a class="prev-page" href="' . add_query_arg('paged', $current_page - 1) . '">&lsaquo;</a>';
                        }
                        
                        // Page numbers
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="paging-input"><span class="tablenav-paging-text">' . $i . '</span></span>';
                            } else {
                                echo '<a class="paging-input" href="' . add_query_arg('paged', $i) . '">' . $i . '</a>';
                            }
                        }
                        
                        // Next page
                        if ($current_page < $total_pages) {
                            echo '<a class="next-page" href="' . add_query_arg('paged', $current_page + 1) . '">&rsaquo;</a>';
                        }
                        
                        echo '</span>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="no-certificates">
            <p><?php _e('No gift certificates found.', 'gift-certificates-fluentforms'); ?></p>
        </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('<?php _e('Coupon code copied to clipboard!', 'gift-certificates-fluentforms'); ?>');
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}

jQuery(document).ready(function($) {
    // Handle resend certificate
    $('.resend-certificate').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('<?php _e('Are you sure you want to resend this gift certificate?', 'gift-certificates-fluentforms'); ?>')) {
            var certificateId = $(this).data('id');
            
            $.post(ajaxurl, {
                action: 'gift_certificate_admin_action',
                action_type: 'resend',
                certificate_id: certificateId,
                nonce: '<?php echo wp_create_nonce('gift_certificate_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Gift certificate resent successfully!', 'gift-certificates-fluentforms'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Failed to resend gift certificate.', 'gift-certificates-fluentforms'); ?>');
                }
            });
        }
    });
    
    // Handle delete certificate
    $('.delete-certificate').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('<?php _e('Are you sure you want to delete this gift certificate? This action cannot be undone.', 'gift-certificates-fluentforms'); ?>')) {
            var certificateId = $(this).data('id');
            
            $.post(ajaxurl, {
                action: 'gift_certificate_admin_action',
                action_type: 'delete',
                certificate_id: certificateId,
                nonce: '<?php echo wp_create_nonce('gift_certificate_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Gift certificate deleted successfully!', 'gift-certificates-fluentforms'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Failed to delete gift certificate.', 'gift-certificates-fluentforms'); ?>');
                }
            });
        }
    });
});
</script>

<style>
.status-active { color: #46b450; }
.status-expired { color: #dc3232; }
.status-pending_delivery { color: #ffb900; }
.status-delivered { color: #0073aa; }

.no-certificates {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 40px;
    text-align: center;
    margin: 20px 0;
}

.no-certificates p {
    font-size: 16px;
    color: #666;
    margin: 0;
}
</style> 