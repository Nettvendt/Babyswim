<?php
/**
 * Template Name: Products calendar for OceanWP theme
 * Author: Knut Sparhell
 *
 * @package OceanWP WordPress theme
 * @author knutsp
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?><?php oceanwp_schema_markup( 'html' ); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<link rel="profile" href="http://gmpg.org/xfn/11">
		<?php wp_head(); ?>
		
		<link rel="stylesheet" id="kursoversikt-css" type="text/css" href="<?=plugin_dir_url('babyswim-kursoversikt/index.php')?>templates/oceanwp-calendar.css?ver=<?=Kursoversikt::$version?>" media="all"/>
		<script src="<?=plugin_dir_url('babyswim-kursoversikt/index.php')?>templates/oceanwp-calendar.js?ver=<?=Kursoversikt::$version?>"></script>
	</head>

	<!-- Begin Body -->
	<body <?php body_class(); ?><?php oceanwp_schema_markup( 'body' ); ?>>

		<?php do_action( 'ocean_before_outer_wrap' ); ?>

		<div id="outer-wrap" class="site clr">

			<?php do_action( 'ocean_before_wrap' ); ?>

			<div id="wrap" class="clr">

				<?php do_action( 'ocean_top_bar' ); ?>

				<?php do_action( 'ocean_header' ); ?>
				
				<?php do_action( 'ocean_before_main' ); ?>

				<main id="main" class="site-main clr"<?php oceanwp_schema_markup( 'main' ); ?>>

					<?php do_action( 'ocean_before_content_wrap' ); ?>

					<div id="content-wrap" class="container clr">

						<?php do_action( 'ocean_before_primary' ); ?>

						<section id="primary" class="content-area clr">

							<?php do_action( 'ocean_before_content' ); ?>

							<div id="content" class="site-content clr">

								<?php do_action( 'ocean_before_content_inner' ); ?>

								<?php while ( have_posts() ) : the_post(); ?>

									<div class="entry-content entry clr">
										<?php the_content(); ?>
										<?php if ( Kursoversikt::$use_tickera ) Kursoversikt::tickera_render(); else Kursoversikt::woo_render(); ?>
									</div><!-- .entry-content -->

								<?php endwhile; ?>

								<?php do_action( 'ocean_after_content_inner' ); ?>

							</div><!-- #content -->

							<?php do_action( 'ocean_after_content' ); ?>

						</section><!-- #primary -->

						<?php do_action( 'ocean_after_primary' ); ?>

					</div><!-- #content-wrap -->

				<?php do_action( 'ocean_after_content_wrap' ); ?>

				<?php get_footer();
