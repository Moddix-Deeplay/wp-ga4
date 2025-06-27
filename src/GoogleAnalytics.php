<?php

namespace WP_GA4;

use Google\Exception;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\RunReportRequest;
use Google_Client;
use Google_Exception;
use Google_Service_Exception;

class GoogleAnalytics
{
    private $client;
    private $analytics;
    private $propertyId;

    public function __construct($jsonKeyPath, $propertyId) {
        $this->propertyId = $propertyId;
        $this->auth($jsonKeyPath);
    }

    private function auth($config): void
    {
        try {
            $this->client = new Google_Client();
            $this->client->setAuthConfig($config);
            $this->client->addScope('https://www.googleapis.com/auth/analytics.readonly');
        } catch (Google_Exception $e) {
            error_log('[GA4] Unable to authenticate: ' . $e->getMessage());
        }
    }

    public function getReport($startDate = '30daysAgo', $endDate = 'today'): array
    {
        $this->analytics = new AnalyticsData($this->client);

        $request = new RunReportRequest([
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'screenPageViews'], ['name' => 'averageSessionDuration']],
            'dimensions' => [['name' => 'pagePath']]
        ]);

        $result = [];
        $logDir = dirname(__FILE__);

        try {
            $response = $this->analytics->properties->runReport('properties/' . $this->propertyId, $request);
            
            //file_put_contents($logDir . '/ga_api_response.json', json_encode($response, JSON_PRETTY_PRINT));
            
            foreach ($response->getRows() as $row) {
                $result[] = [
                    'path' => $row->getDimensionValues()[0]->getValue(),
                    'views' => (int) $row->getMetricValues()[0]->getValue(),
                    'avg_time' => (float) $row->getMetricValues()[1]->getValue()
                ];
            }
            
            //file_put_contents($logDir . '/ga_processed_data.json', json_encode($result, JSON_PRETTY_PRINT));
        } catch (Google_Service_Exception $e) {
            error_log('[GA4] Unable to get report: ' . $e->getMessage());
        }

        return $result;
    }
}