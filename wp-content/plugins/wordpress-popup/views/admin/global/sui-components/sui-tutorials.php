<?php
/**
 * SUI Tutorials.
 *
 * @uses ./sui-icon
 *
 * @package Hustle
 * @since 4.4.6
 */
$hide_docs = apply_filters( 'wpmudev_branding_hide_doc_link', false );
$is_member = 'expired' !== Opt_In_Utils::get_membership_status();

if ( ! $hide_docs && $is_member ) {

	echo '<div id="hustle-tutorials-slider" class="sui-box" style="background-color: transparent; box-shadow: none;"></div>';
}
