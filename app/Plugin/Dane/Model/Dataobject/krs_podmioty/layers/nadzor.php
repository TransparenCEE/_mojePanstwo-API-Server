<?

$data = $this->DB->query("SELECT `krs_nadzorcy`.nazwa, `krs_nadzorcy`.imiona, `krs_osoby`.`id`, YEAR(CURRENT_TIMESTAMP) - YEAR(krs_osoby.data_urodzenia) - (RIGHT(CURRENT_TIMESTAMP, 5) < RIGHT(krs_osoby.data_urodzenia, 5)) as `wiek`
		FROM `krs_nadzorcy` 
		LEFT JOIN `krs_osoby` 
		ON `krs_nadzorcy`.`osoba_id` = `krs_osoby`.`id` 
		WHERE `krs_nadzorcy`.`pozycja_id` = '" . addslashes($id) . "' AND `krs_nadzorcy`.`deleted`='0'
		ORDER BY `krs_nadzorcy`.`ord` ASC LIMIT 100");

$output = array();
foreach ($data as $d) {

    $output[] = array(
        'nazwa' => _ucfirst($d['krs_nadzorcy']['nazwa'] . ' ' . $d['krs_nadzorcy']['imiona']),
        'wiek' => @$d[0]['wiek'],
        'osoba_id' => @$d['krs_osoby']['id'],
    );
}


return $output;