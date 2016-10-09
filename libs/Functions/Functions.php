<?php
namespace Comics;

use Comics\Storage\DB;

Class Functions
{

    static function getComic($parent, $link, $entries)
    {
        /*
        * 	RSS Array Breakdown
        *
        *	$array['channel']['title'] = RSS Title
        * 	$array['channel']['item']['title'] = Item Title
        * 	$array['channel']['item']['description'] = Item Description
        */

        // Establish DB Connection
        $DB = DB::setup(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Set Counter
        $loaded = 0;

        // Check for Existing Comic
        $check = $DB->execute("SELECT * FROM bw_comic_data WHERE parent = ? AND date = ?", [$parent, date('Y-m-d')]);
        if ($check) {
            foreach ($check as $item) {
                if ($loaded < $entries) {
                    $comic_arr[] = ['parent' => $parent, 'title' => $item['title'], 'description' => $item['description']];
                }

                $loaded++;
            }

            return $comic_arr;
        } else {

            // Get Feed
            $feed = file_get_contents($link);

            // Parse XML
            $xml = simplexml_load_string($feed);

            // Use JSON Encoding to Convert XML to Array
            $json = json_encode($xml);
            $array = json_decode($json, true);

            foreach ($array['channel']['item'] as $item) {
                if ($loaded < $entries) {
                    // Create Comic Array
                    $comic_arr[] = ['parent' => $parent, 'title' => $item['title'], 'description' => $item['description']];

                    // Insert Data into DB
                    self::insertComicIntoDB($parent, $item['title'], $item['description']);

                    $loaded++;
                }
            }

            return $comic_arr;
        }
    }

    static function insertComicIntoDB($parent, $title, $description)
    {
        // Insert Data into DB
        $date = date('Y-m-d');
        $DB = DB::setup(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        $DB->execute("INSERT INTO bw_comic_data (parent, date, title, description) VALUES (?, ?, ?, ?)", [$parent, $date, $title, $description]);
    }

    static function addRSSLink($name, $link)
    {
        $DB = DB::setup(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        $DB->execute("INSERT INTO bw_comics (name, link) VALUES (?, ?)", [$name, $link]);

        return ['success' => true];
    }
}

?>