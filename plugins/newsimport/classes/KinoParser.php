<?php
/**
 * Parsing the 'kinodatenCH.xml', 'test_mov.xml' files
 */

/**
 * KinoData Importer class for managing parsing of KinoData files.
 */
class KinoData_Parser {

    /**
     * Parses KinoData data (by KinoData_Parser_SimpleXML)
     *
     * @param string $p_file file name of the kino file
     * @return array
     */
    function parse($p_provider, $p_file, $p_categories) {

        $parser = new KinoData_Parser_SimpleXML;
        $result = $parser->parse($p_provider, $p_file, $p_categories);

        return $result;
    } // fn parse

    //function readCategories() {
    //    ;
    //}

} // class KinoData_Parser

/**
 * KinoData Parser that makes use of the SimpleXML PHP extension.
 */
class KinoData_Parser_SimpleXML {
    /**
     * read new cinema files
     * ... load info about movies
     * ... load info about genres
     * ... load (info about) images
     */


    /**
     * Parses KinoData data (by SimplXML)
     *
     * @param string $p_file file name of the eventdata file
     * @return array
     */
    private function parse($p_provider, $p_file, $p_categories) {

		$events = array();

        libxml_clear_errors();
        $internal_errors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($p_file);

        // halt if loading produces an error
        if (!$xml) {
            $error_msg = "";
            foreach (libxml_get_errors() as $err_line) {
                $error_msg .= $err_line->message;
            }
            libxml_clear_errors();
            return array("correct" => false, "errormsg" => $error_msg);
        }


        // day of event set (event record)
        $recdate = $xml->export->date;

		// $xml->kino is an array of (per-kino) movie sets
		foreach ($xml->kino as $event_kino) {

			// array of events info
			$movie_set = $event_kino->movie;

			// one event is object of simple properties, many of them are empty
			foreach ($movie_set as $event) {
				$event_info = array('provider_id' => $p_provider);

				// Ids
				// number, event id, shall be unique
                // shall be assigned utomatically during insertion into database, but beware that
                // a single screening (cinema/movie/date/time) can probably occur at multiple kino-daten files (at different days)
				$event_info['event_id'] = null;

				// string, movie key, shall be shared among events of particular repeated actions
				$x_filmkey = '' . $event->filmkey;
				if (empty($x_filmkey)) {
					$x_filmkey = null;
				}
				$event_info['turnus_key'] = $x_filmkey;

				// number, movie id
                // will be taken from the movie xml file, according to turnus key,
                // but it may happen that we will not get an id - then shall be autogenerated
				$event_info['turnus_id'] = null;

				// number, location id
				$x_theaterid = '' . $event_kino->theaterid;
				if (empty($x_theaterid)) {
					$x_theaterid = null;
				}
				$event_info['location_id'] = $x_theaterid;


				// Categories
				// * main type fields
				$event_info['event_type_id'] = 7;
				$event_info['event_type'] = 'cinema';

				// Names
				// * main display provider name
				$x_theatername = '' . $event_kino->theatername;
				if (empty($x_theatername)) {
					$x_theatername = null;
				}
				$event_info['event_location'] = $x_theatername;

				// * main display turnus name
				// event name
				$x_filmtitle = '' . $event->filmtitle;
				if (empty($x_filmtitle)) {
					$x_filmtitle = null;
				}
				$event_info['event_name'] = $x_filmtitle;

                $x_theatertelcost = '' . $event->theatertelcost;
                if (empty($x_theatertelcost)) {
                    $x_theatertelcost = null;
                }
                $event_info['fee_price'] = $x_theatertelcost;

				// Locations
				// !!! no country info
				// * main location info
				// town name
				$x_theatertown = '' . $event_kino->theatertown;
				if (empty($x_theatertown)) {
					$x_theatertown = null;
				}
				$event_info['location_town'] = $x_theatertown;

				// zip code
				$x_theaterzip = '' . $event_kino->theaterzip;
				if (empty($x_theaterzip)) {
					$x_theaterzip = null;
				}
				$event_info['location_zip'] = $x_theaterzip;

				// street address, free form, but usually 'street_name house_number'
				$x_theateradress = '' . $event_kino->theateradress;
				if (empty($x_theateradress)) {
					$x_theateradress = null;
				}
				$event_info['location_street'] = $x_theateradress;

				// Date, time
				// * main date-time info

				$event_screening = array();

                $x_prolis = '' . $event->prolis;
                if (!empty($x_prolis)) {
                    foreach(explode(';', trim($x_prolis)) as $one_screening) {
                        $one_screening = trim($one_screening);
                        if (empty($one_screening)) {
                            continue;
                        }
                        // one screening info shall be: "DD.MM.YYYY:HH.mm:L/n/g:"
                        $one_screening_arr = explode(':', $one_screening);
                        if (2 > count($one_screening_arr)) {
                            continue;
                        }
                        $one_screening_date_arr = explode('.', $one_screening_arr[0]);
                        if (3 != count($one_screening_date_arr)) {
                            continue;
                        }
                        $one_screening_date = substr($one_screening_date_arr, 6, 4) . '-' . substr($one_screening_date_arr, 3, 2) . '-' . substr($one_screening_date_arr, 0, 2);
                        if (10 != strlen($one_screening_date)) {
                            continue;
                        }

                        $one_screening_time = trim($one_screening_arr[1]);
                        if (empty($one_screening_time)) {
                            $one_screening_time = null;
                        }

                        $one_screening_lang = null;
                        if (3 <= count($one_screening_arr)) {
                            $one_screening_lang = trim($one_screening_arr);
                            if (empty($one_screening_lang)) {
                                $one_screening_lang = null;
                            }
                        }

                        if (!array_key_exists($one_screening_date, $event_screening)) {
                            $event_screening[$one_screening_date] = array();
                        }
                        $event_screening[$one_screening_date][] = array('time' => $one_screening_time, 'lang' => $one_screening_lang);
                    }
                }

                // no screening means no event
                if (0 == count($event_screening)) {
                    continue;
                }

/*
				$event_info['event_date'] = null;
				$event_info['event_time'] = null;
				$event_info['event_open'] = null;
*/

				// Descriptions
                // shall be taken from the movies file, if the movie is there
				$event_info['event_texts'] = array();

				// Links

				// web link for cinema
				$x_theaterurl = '' . $event_kino->theaterurl;
				if (empty($x_theaterurl)) {
					$x_theaterurl = null;
				}
				$event_info['event_web'] = $x_theaterurl;

				// location email address, none here
				$event_info['event_email'] = null;

				// location phone number
				$x_theaterphone = '' . $event_kino->theaterphone;
				if (empty($x_theaterphone)) {
					$x_theaterphone = null;
				}
				$event_info['event_phone'] = $x_theaterphone;

				// Multimedia
				$event_info['event_images'] = array();

			}


		}

    } // fn parse
} // class KinoData_Parser_SimpleXML




/**
 * MovieData Importer class for managing parsing of MovieData files.
 */
class MovieData_Parser {

    /**
     * Parses MovieData data (by MovieData_Parser_SimpleXML)
     *
     * @param string $p_file file name of the movie file
     * @return array
     */
    function parse($p_provider, $p_file, $p_categories) {

        $parser = new MovieData_Parser_SimpleXML;
        $result = $parser->parse($p_provider, $p_file, $p_categories);

        return $result;
    } // fn parse
} // class MovieData_Parser

/**
 * MovieData Parser that makes use of the SimpleXML PHP extension.
 */
class MovieData_Parser_SimpleXML {

    /**
     * Parses MovieData data (by SimplXML)
     *
     * @param string $p_file file name of the eventdata file
     * @return array
     */
    function parse($p_provider, $p_file, $p_categories) {

		$movies = array();

        libxml_clear_errors();
        $internal_errors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($p_file);

        // halt if loading produces an error
        if (!$xml) {
            $error_msg = "";
            foreach (libxml_get_errors() as $err_line) {
                $error_msg .= $err_line->message;
            }
            libxml_clear_errors();
            return array("correct" => false, "errormsg" => $error_msg);
        }


        // day of event set (event record)
        $recdate = $xml->export->date;

		// $xml->movie is an array of movie descs
		foreach ($xml->movie as $movie_info) {

            $movie_info = array('provider_id' => $p_provider);

            $x_movid = '' . $movie->movid;
            if (empty($x_movid)) {
                $x_movid = null;
            }

            $x_movkey = '' . $movie->movkey;
            if (empty($x_movkey)) {
                $x_movkey = null;
            }

            if (empty($x_movid) || empty($x_movkey)) {
                continue;
            }

            $movie_key = $x_movkey;
            $movie_info['movie_id'] = $x_movid;

            $movie_de = null;
            $movie_fr = null;
            $movie_it = null;

            $x_movcgd = '' . $movie->movcgd;
            if (!empty($x_movcgd)) {
                $movie_de = $x_movcgd;
            }
            if (empty($movie_de)) {
                $x_movsyd = '' . $movie->movsyd;
                if (!empty($x_movsyd)) {
                    $movie_de = $x_movsyd;
                }
            }

            $x_movcgf = '' . $movie->movcgf;
            if (!empty($x_movcgf)) {
                $movie_fr = $x_movcgf;
            }
            if (empty($movie_fr)) {
                $x_movsyf = '' . $movie->movsyf;
                if (!empty($x_movsyf)) {
                    $movie_fr = $x_movsyf;
                }
            }

            $x_movcgi = '' . $movie->movcgi;
            if (!empty($x_movcgi)) {
                $movie_it = $x_movcgi;
            }
            if (empty($movie_it)) {
                $x_movsyi = '' . $movie->movsyi;
                if (!empty($x_movsyi)) {
                    $movie_it = $x_movsyi;
                }
            }

            $movie_info['movie_text'] = array('de' => $movie_de, 'fr' => $movie_fr, 'it' => $movie_it);

            $movies[$movie_key] = $movie_info;

		}

    } // fn parse
} // class MovieData_Parser_SimpleXML








$known_categories = array(
	1 => array('name' => 'theater',
		       'nicks' => array('theater', 'theatre'),
		       ),
	2 => array('name' => 'gallery',
			   'nicks' => array('gallery'),
			   ),
	3 => array('name' => 'exhibition',
			   'nicks' => array('exhibition', 'ausstellung', 'ausstellungen'),
			   ),
	4 => array('name' => 'party',
			   'nicks' => array('party'),
			   ),
	5 => array('name' => 'music',
			   'nicks' => array('music', 'musik'),
			   ),
	6 => array('name' => 'concert',
			   'nicks' => array('concert', 'konzerte'),
			   ),
	7 => array('name' => 'cinema',
			   'nicks' => array('cinema'),
			   ),
);


$provider_id = 2;

$kino_name = 'kinodatenCH.xml';
$kd_parser = new KinoData_Parser();
$kd_parser->parse($provider_id, $kino_name, $known_categories);

$movie_name = 'test_mov.xml';
$md_parser = new MovieData_Parser();
$md_parser->parse($provider_id, $movie_name, $known_categories);


