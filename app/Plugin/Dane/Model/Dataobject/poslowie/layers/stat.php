<?
$data = $this->DB->query("SELECT MIN(pkw_p_glosow) as 'p_glosow_min', MAX(pkw_p_glosow) as 'p_glosow_max', MIN(frekwencja) as 'frekwencja_min', MAX(frekwencja) as 'frekwencja_max', MIN(zbuntowanie) as 'zbuntowanie_min', MAX(zbuntowanie) as 'zbuntowanie_max', MIN(udzial_w_obradach_f) as 'udzial_w_obradach_f_min', MAX(udzial_w_obradach_f) as 'udzial_w_obradach_f_max', MIN(liczba_projektow_ustaw) as 'liczba_projektow_ustaw_min', MAX(liczba_projektow_ustaw) as 'liczba_projektow_ustaw_max', MIN(ilosc_wystapien) as 'ilosc_wystapien_min', MAX(ilosc_wystapien) as 'ilosc_wystapien_max', MIN(liczba_projektow_uchwal) as 'liczba_projektow_uchwal_min', MAX(liczba_projektow_uchwal) as 'liczba_projektow_uchwal_max', MIN(liczba_wnioskow) as 'liczba_wnioskow_min', MAX(liczba_wnioskow) as 'liczba_wnioskow_max' FROM `s_poslowie_kadencje`");

$data = array_merge($data, $this->DB->query("SELECT avg_poparcie_w_okregu, avg_frekwencja, avg_zbuntowanie, avg_projekty_ustaw, avg_projekty_uchwal, avg_wnioski, avg_ilosc_wystapien FROM s_poslowie_stats WHERE id=1"));

return $data;