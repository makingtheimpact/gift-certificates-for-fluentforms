<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Gift Certificates Dashboard', 'gift-certificates-fluentforms'); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="gift-certificate-stats">
        <div class="stat-card">
            <h3><?php _e('Total Certificates', 'gift-certificates-fluentforms'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Active Certificates', 'gift-certificates-fluentforms'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total Value Issued', 'gift-certificates-fluentforms'); ?></h3>
            <div class="stat-number">$<?php echo number_format($stats['total_value'], 2); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total Value Redeemed', 'gift-certificates-fluentforms'); ?></h3>
            <div class="stat-number">$<?php echo number_format($stats['total_redeemed'], 2); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Pending Deliveries', 'gift-certificates-fluentforms'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['pending_delivery']); ?></div>
        </div>
    </div>
    
    <!-- Recent Certificates -->
    <div class="gift-certificate-recent">
        <h2><?php _e('Recent Gift Certificates', 'gift-certificates-fluentforms'); ?></h2>
        
        <?php if (!empty($recent_certificates)): ?>
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
                    <?php foreach ($recent_certificates as $certificate): ?>
                        <tr>
                            <td><strong><?php echo esc_html($certificate->coupon_code); ?></strong></td>
                            <td>
                                <?php echo esc_html($certificate->recipient_name); ?><br>
                                <small><?php echo esc_html($certificate->recipient_email); ?></small>
                            </td>
                            <td>$<?php echo number_format($certificate->original_amount, 2); ?></td>
                            <td>$<?php echo number_format($certificate->current_balance, 2); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($certificate->status); ?>">
                                    <?php echo esc_html(ucfirst($certificate->status)); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($certificate->created_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=gift-certificates-ff-list&action=view&id=' . $certificate->id); ?>" class="button button-small">
                                    <?php _e('View', 'gift-certificates-fluentforms'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No gift certificates found.', 'gift-certificates-fluentforms'); ?></p>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=gift-certificates-ff-list'); ?>" class="button button-primary">
                <?php _e('View All Certificates', 'gift-certificates-fluentforms'); ?>
            </a>
        </p>
    </div>
    
    <!-- Pending Deliveries -->
    <?php if (!empty($pending_deliveries)): ?>
        <div class="gift-certificate-pending">
            <h2><?php _e('Pending Deliveries', 'gift-certificates-fluentforms'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Code', 'gift-certificates-fluentforms'); ?></th>
                        <th><?php _e('Recipient', 'gift-certificates-fluentforms'); ?></th>
                        <th><?php _e('Delivery Date', 'gift-certificates-fluentforms'); ?></th>
                        <th><?php _e('Actions', 'gift-certificates-fluentforms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_deliveries as $certificate): ?>
                        <tr>
                            <td><strong><?php echo esc_html($certificate->coupon_code); ?></strong></td>
                            <td>
                                <?php echo esc_html($certificate->recipient_name); ?><br>
                                <small><?php echo esc_html($certificate->recipient_email); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($certificate->delivery_date)); ?></td>
                            <td>
                                <button class="button button-small resend-certificate" data-id="<?php echo $certificate->id; ?>">
                                    <?php _e('Send Now', 'gift-certificates-fluentforms'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="gift-certificate-actions">
        <h2><?php _e('Quick Actions', 'gift-certificates-fluentforms'); ?></h2>
        
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=gift-certificates-ff-settings'); ?>" class="button button-secondary">
                <?php _e('Settings', 'gift-certificates-fluentforms'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=gift-certificates-ff-help'); ?>" class="button button-secondary">
                <?php _e('How to Use', 'gift-certificates-fluentforms'); ?>
            </a>
            
            <button class="button button-secondary" id="test-email">
                <?php _e('Test Email', 'gift-certificates-fluentforms'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.gift-certificate-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.gift-certificate-recent,
.gift-certificate-pending,
.gift-certificate-actions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin: 20px 0;
}

.status-active { color: #46b450; }
.status-expired { color: #dc3232; }
.status-pending_delivery { color: #ffb900; }
.status-delivered { color: #0073aa; }

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style> 