<?php

/**
* Matomo UserIdの最終アクセス日を取得
*/
public static function getVisitorsLastVisit(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $matomoUrl = "";
        $authToken = "";

        $batchSize = 50; // 1回のリクエストで送る UserId の最大数
        $allVisitorData = [];
        $userIds = [];

        foreach (array_chunk($userIds, $batchSize) as $userBatch) {
            // `OR` を使って UserId を連結
            $segment = urlencode("userId==" . implode(",userId==", $userBatch));

            // APIパラメータを定義
            $apiParams = [
                'module' => 'API',
                'method' => 'Live.getLastVisitsDetails',
                'segment' => $segment,
                'idSite' => 'all',
                'format' => 'JSON',
                'token_auth' => $authToken,
            ];

            // cURL セッションを初期化
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $matomoUrl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiParams));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
            ]);

            // APIリクエストを実行
            $response = curl_exec($ch);

            // cURLエラーハンドリング
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new Exception("Matomo API request failed: $error");
            }

            // HTTPステータスコードを確認
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Matomo API returned HTTP code $httpCode");
            }

            // JSONデコード
            $data = json_decode($response, true);

            // APIレスポンスにエラーが含まれる場合のハンドリング
            if (isset($data['error'])) {
                throw new Exception("Matomo API error: " . $data['error']);
            }

            // レスポンスから訪問データを抽出
            if (!empty($data) && is_array($data)) {
                foreach ($data as $visit) {
                    if (isset($visit['userId'], $visit['visitorId'], $visit['serverDate'], $visit['serverTimePretty'])) {
                        $userId = $visit['userId'];
                        $visitorId = $visit['visitorId'];
                        $lastVisit = $visit['serverDate'] . ' ' . $visit['serverTimePretty'];

                        $allVisitorData[$userId] = [
                            'visitorId' => $visitorId,
                            'lastVisit' => $lastVisit
                        ];
                    }
                }
            }
        }

        return $allVisitorData;
    }
