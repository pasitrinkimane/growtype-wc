<?php

class Growtype_Wc_Google_Sheets
{
    public function service()
    {
        $client = new \Google_Client();
        $client->setApplicationName('Google Sheets API');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $path = __DIR__ . '/credentials/credentials.json';

        $client->setAuthConfig($path);

        return new \Google_Service_Sheets($client);
    }

    public function get_data($spreadsheet_id, $range)
    {
        $service = $this->service();

        $response = $service->spreadsheets_values->get($spreadsheet_id, $range);
        $values = $response->getValues();

        $mapped_data = [];
        if (!empty($values)) {
            foreach ($values as $parent_key => $group) {
                if ($parent_key === 0) {
                    continue;
                }

                foreach ($group as $key => $value) {
                    if ($key === 0) {
                        continue;
                    }

                    $product_name = $group[0];

                    if (empty($product_name)) {
                        continue;
                    }

                    $mapped_data[$product_name][$values[0][$key]] = $value;
                }
            }
        }

        return $mapped_data;
    }
}
