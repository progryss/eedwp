<?php
/**
 * Company Admin Registration Form
 *
 * This template can be overridden by copying it to yourtheme/company-accounts-manager/registration-form.php.
 */

defined('ABSPATH') || exit;

do_action('company_accounts_manager_before_registration_form');
?>

<form method="post" class="cam-registration-form">
    <?php do_action('company_accounts_manager_registration_form_start'); ?>

    <p class="form-row form-row-wide">
        <label for="cam_username"><?php esc_html_e('Username', 'company-accounts-manager'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="cam_username" id="cam_username" autocomplete="username" value="<?php echo (!empty($_POST['cam_username'])) ? esc_attr(wp_unslash($_POST['cam_username'])) : ''; ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="cam_email"><?php esc_html_e('Email address', 'company-accounts-manager'); ?> <span class="required">*</span></label>
        <input type="email" class="input-text" name="cam_email" id="cam_email" autocomplete="email" value="<?php echo (!empty($_POST['cam_email'])) ? esc_attr(wp_unslash($_POST['cam_email'])) : ''; ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="cam_password"><?php esc_html_e('Password', 'company-accounts-manager'); ?> <span class="required">*</span></label>
        <input type="password" class="input-text" name="cam_password" id="cam_password" autocomplete="new-password" required />
    </p>

    <h3><?php esc_html_e('Company Information', 'company-accounts-manager'); ?></h3>

    <p class="form-row form-row-wide">
        <label for="cam_company_name"><?php esc_html_e('Company Name', 'company-accounts-manager'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="cam_company_name" id="cam_company_name" value="<?php echo (!empty($_POST['cam_company_name'])) ? esc_attr(wp_unslash($_POST['cam_company_name'])) : ''; ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="cam_industry"><?php esc_html_e('Industry', 'company-accounts-manager'); ?></label>
        <input type="text" class="input-text" name="cam_industry" id="cam_industry" value="<?php echo (!empty($_POST['cam_industry'])) ? esc_attr(wp_unslash($_POST['cam_industry'])) : ''; ?>" />
    </p>

    <p class="form-row form-row-wide">
        <label for="cam_company_info"><?php esc_html_e('Additional Information', 'company-accounts-manager'); ?></label>
        <textarea name="cam_company_info" id="cam_company_info" rows="4"><?php echo (!empty($_POST['cam_company_info'])) ? esc_textarea(wp_unslash($_POST['cam_company_info'])) : ''; ?></textarea>
    </p>

    <?php do_action('company_accounts_manager_registration_form'); ?>

    <p class="form-row">
        <?php wp_nonce_field('cam_register', 'cam_register_nonce'); ?>
        <button type="submit" class="button" name="cam_register" value="<?php esc_attr_e('Register', 'company-accounts-manager'); ?>"><?php esc_html_e('Register', 'company-accounts-manager'); ?></button>
    </p>

    <?php do_action('company_accounts_manager_registration_form_end'); ?>
</form>

<?php do_action('company_accounts_manager_after_registration_form'); ?> 