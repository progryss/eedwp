<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package storefront
 */

?>

		</div><!-- .col-full -->
	</div><!-- #content -->

	<?php do_action( 'storefront_before_footer' ); ?>
<div class="upper-footer-main">
<div class="col-full">
		<?php if ( is_active_sidebar( 'upper-footer' ) ) : ?>
    <div class="upper-footer-widgets">
        <?php dynamic_sidebar( 'upper-footer' ); ?>
    </div>
	</div>
</div>

	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="col-full">
<?php endif; ?>
		<div class="footer-widgets custom-footer-widgets">
				<div class="footer-widgets-row">
					<?php for ( $i = 1; $i <= 7; $i++ ) : ?>
						<div class="col footer-col-<?php echo $i; ?>">
							<?php if ( is_active_sidebar( 'footer-' . $i ) ) : ?>
								<?php dynamic_sidebar( 'footer-' . $i ); ?>
							<?php endif; ?>
						</div>
					<?php endfor; ?>
				</div>
						<div class="footer-copyright">
				&copy; <?php echo date('Y'); ?> <a href="/">EED</a>, All Rights Reserved.
			</div>

			</div>

			<?php
			/**
			 * Functions hooked in to storefront_footer action
			 *
			 * @hooked storefront_footer_widgets - 10
			 * @hooked storefront_credit         - 20
			 */
			do_action( 'storefront_footer' );
			?>


		</div><!-- .col-full -->
	</footer><!-- #colophon -->

	<?php do_action( 'storefront_after_footer' ); ?>



</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
