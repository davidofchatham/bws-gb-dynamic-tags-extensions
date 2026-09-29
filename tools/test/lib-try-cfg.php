<?php
/**
 * Each try_ template's attempt-walk config — TagTemplateRegistry::try_loop_cfg() per
 * template, written out.
 *
 * preview-label-test.php drives the try_ preview (and the real walk) with these, and
 * cannot load the descriptors: they register through TagTemplateRegistry under a
 * WordPress bootstrap that harness does not have. control-order-test.php DOES load them,
 * and pins every row here against try_loop_cfg() of the live descriptor (§8c), both
 * directions, so this copy cannot drift from the templates unobserved.
 *
 * @package BWS_Dynamic_Tags
 */

const TRY_CFG = array(
	'text'            => array( 'per_slot_key' => true,  'per_slot_use' => true,  'flat_per_slot_use' => true, 'no_key_uses' => array( 'title', 'fixed' ),     'default_use' => 'key',     'collapse' => false ),
	'content'         => array( 'per_slot_key' => true,  'per_slot_use' => true,  'flat_per_slot_use' => true, 'no_key_uses' => array( 'content', 'excerpt' ), 'default_use' => 'content', 'collapse' => true ),
	'title'           => array( 'per_slot_key' => false, 'per_slot_use' => false, 'flat_per_slot_use' => false, 'no_key_uses' => array(),                       'default_use' => '',        'collapse' => false ),
	'permalink'       => array( 'per_slot_key' => false, 'per_slot_use' => false, 'flat_per_slot_use' => false, 'no_key_uses' => array(),                       'default_use' => '',        'collapse' => true ),
	'image'           => array( 'per_slot_key' => true,  'per_slot_use' => true,  'flat_per_slot_use' => true, 'no_key_uses' => array( 'featured' ),           'default_use' => 'key',     'collapse' => true ),
	'datetime_single' => array( 'per_slot_key' => false, 'per_slot_use' => false, 'flat_per_slot_use' => false, 'no_key_uses' => array(),                       'default_use' => '',        'collapse' => false ),
	'datetime_range'  => array( 'per_slot_key' => false, 'per_slot_use' => false, 'flat_per_slot_use' => false, 'no_key_uses' => array(),                       'default_use' => '',        'collapse' => false ),
	'email'           => array( 'per_slot_key' => true,  'per_slot_use' => true,  'flat_per_slot_use' => false, 'no_key_uses' => array( 'fixed' ),            'default_use' => 'key',     'collapse' => false ),
	'phone'           => array( 'per_slot_key' => true,  'per_slot_use' => true,  'flat_per_slot_use' => false, 'no_key_uses' => array( 'fixed' ),            'default_use' => 'key',     'collapse' => false ),
);
