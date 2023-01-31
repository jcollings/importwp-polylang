<?php

use ImportWP\Common\Addon\AddonBaseGroup;
use ImportWP\Common\Addon\AddonBasePanel;
use ImportWP\Common\Addon\AddonInterface;
use ImportWP\Common\Addon\AddonPanelDataApi;

iwp_register_importer_addon('Polylang', 'polylang', function (AddonInterface $addon) {

    $post_type = (array)$addon->importer_model()->getSetting('post_type');
    if (!pll_is_translated_post_type($post_type)) {
        return;
    }

    $addon->register_panel('Polylang', 'polylang', function (AddonBasePanel $panel) {

        $languages = pll_the_languages([
            'raw' => 1
        ]);

        $languages = array_reduce($languages, function ($carry, $item) {
            $carry[] = [
                'value' => $item['slug'],
                'label' => $item['name']
            ];
            return $carry;
        }, []);

        $panel->register_field('language', 'language', [
            'options' => $languages
        ])->save(false);


        $panel->register_group('Translations', '_translation', function (AddonBaseGroup $group) {
            $group->register_field('Translation', 'translation', [
                'default' => '',
                'tooltip' => __('Set this for the post it belongs to', 'importwp')
            ])->save(false);
            $group->register_field('Translation Field Type', '_translation_type', [
                'default' => 'id',
                'options' => [
                    ['value' => 'id', 'label' => 'ID'],
                    ['value' => 'slug', 'label' => 'Slug'],
                    ['value' => 'name', 'label' => 'Name'],
                    ['value' => 'column', 'label' => 'Reference Column']
                ],
                'type' => 'select',
                'tooltip' => __('Select how the translation field should be handled', 'importwp')
            ])->save(false);
            $group->register_field('Translation Reference Column', '_translation_ref', [
                'condition' => ['_translation_type', '==', 'column'],
                'tooltip' => __('Select the column/node that the translation field is referencing', 'importwp')
            ])->save(false);
        });

        $panel->save(function (AddonPanelDataApi $api) {

            $meta = $api->get_meta();
            if (empty($meta)) {
                return;
            }

            $parent_id = 0;
            $post_type = $api->importer_model()->getSetting('post_type');

            $parent_type = isset($meta['_translation_type'], $meta['_translation_type']['value']) ? $meta['_translation_type']['value'] : false;

            switch ($parent_type) {
                case 'name':
                case 'slug':
                    // name or slug
                    $page = get_posts(array('name' => sanitize_title($meta['translation']['value']), 'post_type' => $post_type));
                    if ($page) {
                        $parent_id = intval($page[0]->ID);
                    }
                    break;
                case 'id':
                    $parent_id = intval($meta['translation']['value']);
                    break;
                case 'column':

                    // flag this on the post for future searches
                    $api->update_meta('_iwp_pll_post_translation', $meta['_translation_ref']['value']);

                    $temp_id = iwp_pll_get_post_by_cf('_iwp_pll_post_translation', $meta['translation']['value'], $post_type, $api->object_id());
                    if (intval($temp_id > 0)) {
                        $parent_id = intval($temp_id);
                    }

                    break;
            }

            // save language
            if (!empty($meta['language']['value'])) {
                pll_set_post_language($api->object_id(), $meta['language']['value']);
            }

            if ($parent_id !== $api->object_id() && $parent_id > 0) {

                $parent_language = pll_get_post_language($parent_id);
                $translations = pll_get_post_translations($parent_id);
                $translations[$meta['language']['value']] = $api->object_id();

                if ($parent_language) {
                    $translations[$parent_language] = $parent_id;
                }

                pll_save_post_translations($translations);
            }
        });
    });
});

function iwp_pll_get_post_by_cf($field, $value, $post_type, $id)
{

    $query = new \WP_Query(array(
        'post_type' => $post_type,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'cache_results' => false,
        'update_post_meta_cache' => false,
        'post__not_in' => [$id],
        'meta_query' => array(
            array(
                'key' => $field,
                'value' => $value
            )
        ),
        'post_status' => 'any'
    ));
    if ($query->have_posts()) {
        return $query->posts[0];
    }
    return false;
}
