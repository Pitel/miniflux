<?php

namespace Model\Item;

require_once __DIR__.'/../vendor/Readability/Readability.php';
require_once __DIR__.'/../vendor/PicoFeed/Grabber.php';
require_once __DIR__.'/../vendor/PicoFeed/Filter.php';

use PicoDb\Database;

// Get all items without filtering
function get_everything()
{
    return Database::get('db')
        ->table('items')
        ->columns(
            'items.id',
            'items.title',
            'items.updated',
            'items.url',
            'items.enclosure',
            'items.enclosure_type',
            'items.bookmark',
            'items.feed_id',
            'items.status',
            'items.content',
            'feeds.site_url',
            'feeds.title AS feed_title'
        )
        ->join('feeds', 'id', 'feed_id')
        ->in('status', array('read', 'unread'))
        ->orderBy('updated', 'desc')
        ->findAll();
}

// Get everthing since date (timestamp)
function get_everything_since($timestamp)
{
    return Database::get('db')
        ->table('items')
        ->columns(
            'items.id',
            'items.title',
            'items.updated',
            'items.url',
            'items.enclosure',
            'items.enclosure_type',
            'items.bookmark',
            'items.feed_id',
            'items.status',
            'items.content',
            'feeds.site_url',
            'feeds.title AS feed_title'
        )
        ->join('feeds', 'id', 'feed_id')
        ->in('status', array('read', 'unread'))
        ->gte('updated', $timestamp)
        ->orderBy('updated', 'desc')
        ->findAll();
}

// Get a list of [item_id => status,...]
function get_all_status()
{
    return Database::get('db')
        ->table('items')
        ->in('status', array('read', 'unread'))
        ->orderBy('updated', 'desc')
        ->listing('id', 'status');
}

// Get all items by status
function get_all($status, $offset = null, $limit = null, $order_column = 'updated', $order_direction = 'desc')
{
    return Database::get('db')
        ->table('items')
        ->columns(
            'items.id',
            'items.title',
            'items.updated',
            'items.url',
            'items.enclosure',
            'items.enclosure_type',
            'items.bookmark',
            'items.feed_id',
            'items.status',
            'items.content',
            'feeds.site_url',
            'feeds.title AS feed_title'
        )
        ->join('feeds', 'id', 'feed_id')
        ->eq('status', $status)
        ->orderBy($order_column, $order_direction)
        ->offset($offset)
        ->limit($limit)
        ->findAll();
}

// Get the number of items per status
function count_by_status($status)
{
    return Database::get('db')
        ->table('items')
        ->eq('status', $status)
        ->count();
}

// Get the number of bookmarks
function count_bookmarks()
{
    return Database::get('db')
        ->table('items')
        ->eq('bookmark', 1)
        ->in('status', array('read', 'unread'))
        ->count();
}

// Get all bookmarks
function get_bookmarks($offset = null, $limit = null)
{
    return Database::get('db')
        ->table('items')
        ->columns(
            'items.id',
            'items.title',
            'items.updated',
            'items.url',
            'items.enclosure',
            'items.enclosure_type',
            'items.bookmark',
            'items.status',
            'items.content',
            'items.feed_id',
            'feeds.site_url',
            'feeds.title AS feed_title'
        )
        ->join('feeds', 'id', 'feed_id')
        ->in('status', array('read', 'unread'))
        ->eq('bookmark', 1)
        ->orderBy('updated', \Model\Config\get('items_sorting_direction'))
        ->offset($offset)
        ->limit($limit)
        ->findAll();
}

// Get the number of items per feed
function count_by_feed($feed_id)
{
    return Database::get('db')
        ->table('items')
        ->eq('feed_id', $feed_id)
        ->in('status', array('unread', 'read'))
        ->count();
}

// Get all items per feed
function get_all_by_feed($feed_id, $offset = null, $limit = null, $order_column = 'updated', $order_direction = 'desc')
{
    return Database::get('db')
        ->table('items')
        ->columns(
            'items.id',
            'items.title',
            'items.updated',
            'items.url',
            'items.enclosure',
            'items.enclosure_type',
            'items.feed_id',
            'items.status',
            'items.content',
            'items.bookmark',
            'feeds.site_url'
        )
        ->join('feeds', 'id', 'feed_id')
        ->in('status', array('unread', 'read'))
        ->eq('feed_id', $feed_id)
        ->orderBy($order_column, $order_direction)
        ->offset($offset)
        ->limit($limit)
        ->findAll();
}

// Get one item by id
function get($id)
{
    return Database::get('db')
        ->table('items')
        ->eq('id', $id)
        ->findOne();
}

// Get item naviguation (next/prev items)
function get_nav($item, $status = array('unread'), $bookmark = array(1, 0), $feed_id = null)
{
    $query = Database::get('db')
        ->table('items')
        ->columns('id', 'status', 'title', 'bookmark')
        ->neq('status', 'removed')
        ->orderBy('updated', \Model\Config\get('items_sorting_direction'));

    if ($feed_id) $query->eq('feed_id', $feed_id);

    $items = $query->findAll();

    $next_item = null;
    $previous_item = null;

    for ($i = 0, $ilen = count($items); $i < $ilen; $i++) {

        if ($items[$i]['id'] == $item['id']) {

            if ($i > 0) {

                $j = $i - 1;

                while ($j >= 0) {

                    if (in_array($items[$j]['status'], $status) && in_array($items[$j]['bookmark'], $bookmark)) {
                        $previous_item = $items[$j];
                        break;
                    }

                    $j--;
                }
            }

            if ($i < ($ilen - 1)) {

                $j = $i + 1;

                while ($j < $ilen) {

                    if (in_array($items[$j]['status'], $status) && in_array($items[$j]['bookmark'], $bookmark)) {
                        $next_item = $items[$j];
                        break;
                    }

                    $j++;
                }
            }

            break;
        }
    }

    return array(
        'next' => $next_item,
        'previous' => $previous_item
    );
}

// Change item status to removed and clear content
function set_removed($id)
{
    return Database::get('db')
        ->table('items')
        ->eq('id', $id)
        ->save(array('status' => 'removed', 'content' => ''));
}

// Change item status to read
function set_read($id)
{
    return Database::get('db')
        ->table('items')
        ->eq('id', $id)
        ->save(array('status' => 'read'));
}

// Change item status to unread
function set_unread($id)
{
    return Database::get('db')
        ->table('items')
        ->eq('id', $id)
        ->save(array('status' => 'unread'));
}

// Change item status to "read", "unread" or "removed"
function set_status($status, array $items)
{
    if (! in_array($status, array('read', 'unread', 'removed'))) return false;

    return Database::get('db')
        ->table('items')
        ->in('id', $items)
        ->save(array('status' => $status));
}

// Enable/disable bookmark flag
function set_bookmark_value($id, $value)
{
    return Database::get('db')
        ->table('items')
        ->eq('id', $id)
        ->save(array('bookmark' => $value));
}

// Swap item status read <-> unread
function switch_status($id)
{
    $item = Database::get('db')
        ->table('items')
        ->columns('status')
        ->eq('id', $id)
        ->findOne();

    if ($item['status'] == 'unread') {

        Database::get('db')
            ->table('items')
            ->eq('id', $id)
            ->save(array('status' => 'read'));

        return 'read';
    }
    else {

        Database::get('db')
            ->table('items')
            ->eq('id', $id)
            ->save(array('status' => 'unread'));

        return 'unread';
    }

    return '';
}

// Mark all unread items as read
function mark_all_as_read()
{
    return Database::get('db')
        ->table('items')
        ->eq('status', 'unread')
        ->save(array('status' => 'read'));
}

// Mark all read items to removed
function mark_all_as_removed()
{
    return Database::get('db')
        ->table('items')
        ->eq('status', 'read')
        ->eq('bookmark', 0)
        ->save(array('status' => 'removed', 'content' => ''));
}

// Mark only specified items as read
function mark_items_as_read(array $items_id)
{
    Database::get('db')->startTransaction();

    foreach ($items_id as $id) {
        set_read($id);
    }

    Database::get('db')->closeTransaction();
}

// Mark all items of a feed as read
function mark_feed_as_read($feed_id)
{
    return Database::get('db')
        ->table('items')
        ->eq('status', 'unread')
        ->eq('feed_id', $feed_id)
        ->update(array('status' => 'read'));
}

// Mark all read items to removed after X days
function autoflush()
{
    $autoflush = (int) \Model\Config\get('autoflush');

    if ($autoflush > 0) {

        // Mark read items removed after X days
        Database::get('db')
            ->table('items')
            ->eq('bookmark', 0)
            ->eq('status', 'read')
            ->lt('updated', strtotime('-'.$autoflush.'day'))
            ->save(array('status' => 'removed', 'content' => ''));
    }
    else if ($autoflush === -1) {

        // Mark read items removed immediately
        Database::get('db')
            ->table('items')
            ->eq('bookmark', 0)
            ->eq('status', 'read')
            ->save(array('status' => 'removed', 'content' => ''));
    }
}

// Update all items
function update_all($feed_id, array $items, $grabber = false)
{
    $nocontent = (bool) \Model\Config\get('nocontent');

    $items_in_feed = array();

    $db = Database::get('db');
    $db->startTransaction();

    foreach ($items as $item) {

        \PicoFeed\Logging::log('Item => '.$item->id.' '.$item->url);

        // Item parsed correctly?
        if ($item->id && $item->url) {

            \PicoFeed\Logging::log('Item parsed correctly');

            // Insert only new item
            if ($db->table('items')->eq('id', $item->id)->count() !== 1) {

                \PicoFeed\Logging::log('Item added to the database');

                if (! $item->content && ! $nocontent && $grabber) {
                    $item->content = download_content_url($item->url);
                }

                $db->table('items')->save(array(
                    'id' => $item->id,
                    'title' => $item->title,
                    'url' => $item->url,
                    'updated' => $item->updated,
                    'author' => $item->author,
                    'content' => $nocontent ? '' : $item->content,
                    'status' => 'unread',
                    'feed_id' => $feed_id,
                    'enclosure' => isset($item->enclosure) ? $item->enclosure : null,
                    'enclosure_type' => isset($item->enclosure_type) ? $item->enclosure_type : null,
                ));
            }
            else {
                \PicoFeed\Logging::log('Item already in the database');
            }

            // Items inside this feed
            $items_in_feed[] = $item->id;
        }
    }

    // Remove from the database items marked as "removed"
    // and not present inside the feed
    if (! empty($items_in_feed)) {

        $removed_items = $db
            ->table('items')
            ->columns('id')
            ->notin('id', $items_in_feed)
            ->eq('status', 'removed')
            ->eq('feed_id', $feed_id)
            ->desc('updated')
            ->findAllByColumn('id');

        // Keep a buffer of 2 items
        // It's workaround for buggy feeds (cache issue with some Wordpress plugins)
        if (is_array($removed_items)) {

            $items_to_remove = array_slice($removed_items, 2);

            if (! empty($items_to_remove)) {

                $nb_items = count($items_to_remove);
                \PicoFeed\Logging::log('There is '.$nb_items.' items to remove');

                // Handle the case when there is a huge number of items to remove
                // Sqlite have a limit of 1000 sql variables by default
                // Avoid the error message "too many SQL variables"
                // We remove old items by batch of 500 items
                $chunks = array_chunk($items_to_remove, 500);

                foreach ($chunks as $chunk) {

                    $db->table('items')
                        ->in('id', $chunk)
                        ->eq('status', 'removed')
                        ->eq('feed_id', $feed_id)
                        ->remove();
                }
            }
        }
    }

    \PicoFeed\Logging::log('Db transaction => '.($db->getConnection()->inTransaction() ? 'ok' : 'rollback'));

    $db->closeTransaction();
}

// Download content from an URL
function download_content_url($url)
{
    $client = \PicoFeed\Client::create();
    $client->url = $url;
    $client->timeout = HTTP_TIMEOUT;
    $client->user_agent = \Model\Config\HTTP_FAKE_USERAGENT;
    $client->execute();

    $html = $client->getContent();

    if (! empty($html)) {

        // Try first with PicoFeed grabber and with Readability after
        $grabber = new \PicoFeed\Grabber($url, $html, $client->getEncoding());
        $content = '';

        if ($grabber->parse()) {
            $content = $grabber->content;
        }

        if (empty($content)) {
            $content = download_content_readability($grabber->html, $url);
        }

        // Filter content
        $filter = new \PicoFeed\Filter($content, $url);
        return $filter->execute();
    }

    return '';
}

// Download content from item ID
function download_content_id($item_id)
{
    $item = get($item_id);
    $content = download_content_url($item['url']);

    if (! empty($content)) {

        if (! \Model\Config\get('nocontent')) {

            // Save content
            Database::get('db')
                ->table('items')
                ->eq('id', $item['id'])
                ->save(array('content' => $content));
        }

        \Model\Config\write_debug();

        return array(
            'result' => true,
            'content' => $content
        );
    }

    \Model\Config\write_debug();

    return array(
        'result' => false,
        'content' => ''
    );
}

// Download content with Readability PHP port
function download_content_readability($content, $url)
{
    if (! empty($content)) {

        $readability = new \Readability($content, $url);

        if ($readability->init()) {
            return $readability->getContent()->innerHTML;
        }
    }

    return '';
}
