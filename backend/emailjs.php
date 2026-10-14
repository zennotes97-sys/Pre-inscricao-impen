<?php

declare(strict_types=1);

function sendEmailNotifications(mysqli $conn, array $formData): array
{
    try {
        $statement = $conn->prepare(
            'SELECT public_key, private_key, service_primary, service_fallback,
                    template_admin, template_applicant
             FROM configuracao_emailjs
             WHERE id = 1'
        );
        $statement->execute();
        $configuration = $statement->get_result()->fetch_assoc();
        $statement->close();
    } catch (mysqli_sql_exception $error) {
        error_log('Não foi possível ler a configuração EmailJS da base de dados: ' . $error->getMessage());
        return [
            'admin' => false,
            'applicant' => false,
            'message' => 'A pré-inscrição foi guardada, mas a configuração de email não está disponível.',
        ];
    }

    if (
        !$configuration ||
        $configuration['public_key'] === '' ||
        $configuration['service_primary'] === '' ||
        $configuration['template_admin'] === '' ||
        $configuration['template_applicant'] === '' ||
        !function_exists('curl_init')
    ) {
        error_log('A configuração EmailJS na base de dados está incompleta ou a extensão cURL não está disponível.');
        return [
            'admin' => false,
            'applicant' => false,
            'message' => 'A pré-inscrição foi guardada, mas o envio de email não está configurado.',
        ];
    }

    $publicKey = $configuration['public_key'];
    $emailjsPrivateKey = $configuration['private_key'];
    $services = array_values(array_unique(array_filter([
        $configuration['service_primary'],
        $configuration['service_fallback'] ?? '',
    ])));
    $templates = [
        'admin' => $configuration['template_admin'],
        'applicant' => $configuration['template_applicant'],
    ];

    $results = ['admin' => false, 'applicant' => false];
    $requestCount = 0;

    foreach ($templates as $recipient => $templateId) {
        foreach ($services as $serviceId) {
            if ($requestCount > 0) {
                sleep(1);
            }
            $requestCount++;

            $payload = [
                'service_id' => $serviceId,
                'template_id' => $templateId,
                'user_id' => $publicKey,
                'template_params' => $formData,
            ];
            if ($emailjsPrivateKey !== false && $emailjsPrivateKey !== '') {
                $payload['accessToken'] = $emailjsPrivateKey;
            }

            $curl = curl_init('https://api.emailjs.com/api/v1.0/email/send');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
            ]);

            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($response !== false && $status >= 200 && $status < 300) {
                $results[$recipient] = true;
                break;
            }

            error_log(
                'Falha no envio EmailJS (' . $recipient . ', HTTP ' . $status . ')' .
                ($curlError !== '' ? ': ' . $curlError : '.')
            );
        }
    }

    if ($results['admin'] && $results['applicant']) {
        $message = 'Foram enviados um aviso à secretaria e uma confirmação para o seu email.';
    } elseif ($results['admin']) {
        $message = 'O aviso foi enviado à secretaria, mas não foi possível enviar a confirmação ao seu email.';
    } elseif ($results['applicant']) {
        $message = 'A confirmação foi enviada ao seu email, mas não foi possível avisar a secretaria.';
    } else {
        $message = 'A pré-inscrição foi guardada, mas não foi possível enviar os emails. A secretaria poderá consultar os dados no sistema.';
    }

    return [
        'admin' => $results['admin'],
        'applicant' => $results['applicant'],
        'message' => $message,
    ];
}
