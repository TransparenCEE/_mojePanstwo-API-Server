<?php

App::uses('OrganizacjeDzialaniaPisma', 'Dane.Model');
App::uses('OrganizacjeDzialaniaTematy', 'Dane.Model');
App::uses('PismoSzablon', 'Dane.Model');
App::uses('OrganizacjeDzialania', 'Dane.Model');
App::uses('Temat', 'Dane.Model');
App::uses('S3Component', 'Controller.Component');

App::uses('CakeSession', 'Model/Datasource');

class KrsPodmioty extends AppModel {

    public $useTable = false;

    public function pobierz_nowy_odpis($data, $id, $dataset) {
        $db = ConnectionManager::getDataSource('default');
        $id = (int) $id;

        $results = $db->query("
            SELECT id, complete, priority
            FROM krs_files
            WHERE
              status!='-1' AND 
              krs_pozycje_id = $id AND
              (
                complete = '0'
                OR
                (
                  complete = '1' AND
                  TIMESTAMPDIFF(HOUR, complete_ts, NOW()) <= 24
                )
              )
            ORDER BY complete_ts DESC 
            LIMIT 1
        ");
        
        if($results[0]['krs_files']) {
            $row = $results[0]['krs_files'];
            if($row['complete'] == '1') {
                $message = 'Odpis był już ostatnio aktualizowany';
            } else if($row['complete'] == '0' && $row['priority']) {
                $message = 'Odpis oczekuje w kolejce na pobranie';
            } else {
                $db->query("UPDATE krs_files SET priority = 1 WHERE id = ".$row['id']);
                $message = 'Priorytet został zwiększony i odpis zostanie wkrótce pobrany';
            }
        } else {
            $db->query("INSERT INTO krs_files(krs_pozycje_id, priority) VALUES ($id, 1)");
            $message = 'Odpis został dodany do kolejki jako priorytet i zostanie wkrótce pobrany';
        }

        return array(
            'flash_message' => $message
        );
    }

}