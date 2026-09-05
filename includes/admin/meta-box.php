<?php
/**
 * ADMIN: PER-POST LOCK META BOX - Scheduler Pro v2.6
 */

if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function() {
    add_meta_box(
        'sp_lock_date_box',
        '🔒 ' . __('Scheduler Pro: Locking', 'scheduler-pro'),
        'sp_render_lock_meta_box',
        'post',
        'side',
        'high'
    );
});

function sp_render_lock_meta_box($post) {
    $value = get_post_meta($post->ID, '_sp_lock_planning', true);
    wp_nonce_field('sp_lock_nonce', 'sp_lock_nonce_field');
    ?>
    <label class="sp-meta-box-label">
        <input type="checkbox"
               name="sp_lock_planning"
               value="1"
               <?php checked($value, '1'); ?>>
        <strong><?php esc_html_e('Lock the publication date', 'scheduler-pro'); ?></strong>
    </label>
    <p class="description">
        <?php esc_html_e("Prevents the Scheduler from automatically changing this post's date.", 'scheduler-pro'); ?>
    </p>
    <?php
}

add_action('save_post', function($post_id) {
    if (!isset($_POST['sp_lock_nonce_field']) || !wp_verify_nonce($_POST['sp_lock_nonce_field'], 'sp_lock_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, '_sp_lock_planning', isset($_POST['sp_lock_planning']) ? '1' : '0');
});
