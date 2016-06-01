<?php

defined( 'ABSPATH' ) or exit;

/**
 * @param $groupings
 * @param $interest_category
 *
 * @return object|null
 */
function mc4wp_400_find_grouping_for_interest_category( $groupings, $interest_category ) {

    foreach( $groupings as $grouping ) {
        if( $grouping->name === $interest_category->title ) {
            return $grouping;
        }
    }

    return null;
}

/**
 * @param $groups
 * @param $interest
 *
 * @return object|null
 */
function mc4wp_400_find_group_for_interest( $groups, $interest ) {
    foreach( $groups as $group_id => $group_name ) {
        if( $group_name === $interest->name ) {
            return (object) array(
                'name' => $group_name,
                'id' => $group_id
            );
        }
    }

    return null;
}

// in case the migration is _very_ late to the party
if( ! class_exists( 'MC4WP_API_v3' ) ) {
    return;
}

$options = get_option( 'mc4wp', array() );
if( empty( $options['api_key'] ) ) {
    return;
}

// get current state from transient
$lists = get_transient( 'mc4wp_mailchimp_lists_fallback' );

if( empty( $lists ) ) {
    return;
}

@set_time_limit(600);
$api_v3 = new MC4WP_API_v3( $options['api_key'] );
$map = array();

foreach( $lists as $list ) {

    // no groupings? easy!
    if( empty( $list->groupings ) ) {
        continue;
    }

    // fetch (new) interest categories for this list
    $interest_categories = $api_v3->get_list_interest_categories( $list->id );

    foreach( $interest_categories as $interest_category ) {

        // compare interest title with grouping name, if it matches, get new id.
        $grouping = mc4wp_400_find_grouping_for_interest_category( $list->groupings, $interest_category );
        if( ! $grouping ) {
            continue;
        }

        $groups = array();
        $interests = $api_v3->get_list_interest_category_interests( $list->id, $interest_category->id );
        foreach( $interests as $interest ) {
            $group = mc4wp_400_find_group_for_interest( $grouping->groups, $interest );

            if( $group ) {
                $groups[ $group->id ] = $interest->id;
                $groups[ $group->name ] = $interest->id;
            }
        }

        $map[ (string) $grouping->id ] = array(
            'id' => $interest_category->id,
            'groups' => $groups,
        );
    }

}

if( ! empty( $map ) ) {
    update_option( 'mc4wp_groupings_map', $map );
}