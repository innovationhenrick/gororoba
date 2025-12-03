<?php
header('Content-Type: application/json');

// Allow CORS if needed
// header("Access-Control-Allow-Origin: *");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        $data = $_POST;
    }

    $nome = filter_var($data['nome'] ?? '', FILTER_SANITIZE_STRING);
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);

    // Answers
    $answers = [
        'q1' => $data['q1'] ?? 'Não respondido',
        'q2' => $data['q2'] ?? 'Não respondido',
        'q3' => $data['q3'] ?? 'Não respondido',
        'q4' => $data['q4'] ?? 'Não respondido',
        'q5' => $data['q5'] ?? 'Não respondido',
        'q6' => $data['q6'] ?? 'Não respondido'
    ];

    // Score Calculation
    $correctAnswers = [
        'q1' => 'C',
        'q2' => 'A',
        'q3' => 'B',
        'q4' => 'B',
        'q5' => 'B',
        'q6' => 'B'
    ];

    $score = 0;
    $totalQuestions = count($correctAnswers);
    $correctCount = 0;

    foreach ($correctAnswers as $key => $correct) {
        if (isset($answers[$key]) && $answers[$key] === $correct) {
            $correctCount++;
        }
    }

    $score = round(($correctCount / $totalQuestions) * 100);

    if (empty($nome) || empty($email)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Nome e E-mail são obrigatórios."]);
        exit;
    }

    // --- Gemini API Integration ---
    $apiKey = getenv('GEMINI_API_KEY') ?: $_SERVER['GEMINI_API_KEY'];
    $aiReport = "Relatório não gerado (Erro na API).";

    if ($apiKey) {
        $prompt = "Você é um especialista em Curadoria de IA e Comunicação Executiva. O usuário '$nome' respondeu a um teste 'Anti-Gororoba' (Anti-Alucinação e Anti-Burocracia).
        
        Ele acertou $correctCount de $totalQuestions questões (Nota: $score/100).

        Aqui estão as respostas dele para 6 cenários:
        1. Fonte Fantasma: {$answers['q1']} (Correta: C)
        2. Falso Consenso: {$answers['q2']} (Correta: A)
        3. Conselho Genérico: {$answers['q3']} (Correta: B)
        4. Tom Deslocado: {$answers['q4']} (Correta: B)
        5. Enrolação Corporativa: {$answers['q5']} (Correta: B)
        6. Lista Infinita: {$answers['q6']} (Correta: B)

        TAREFA:
        Gere um relatório de feedback curto e direto (máximo 200 palavras) em formato HTML (sem tags html/body, apenas divs, p, strong, h3).
        1. Dê uma 'Nota de Curadoria' de 0 a 100 baseada nos acertos.
        2. Elogie se foi bem, ou dê uma dica ácida/engraçada sobre 'parar de comer gororoba' se foi mal.
        3. Destaque 1 ponto forte ou fraco.
        4. Termine com um CTA motivacional para ele liderar a era da IA.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $apiKey;

        $requestBody = [
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ]
        ];

        $maxRetries = 3;
        $retryCount = 0;
        $response = null;
        $httpCode = 0;
        $curlError = '';

        do {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 503) {
                $retryCount++;
                if ($retryCount < $maxRetries) {
                    sleep(2); // Wait 2 seconds before retrying
                }
            } else {
                break; // Exit loop if not 503
            }
        } while ($retryCount < $maxRetries);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            $aiReport = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? "Erro ao processar resposta da IA.";
            // Clean markdown code blocks and bold markers
            $aiReport = preg_replace('/^```html|```$/m', '', $aiReport);
            $aiReport = str_replace(['**', '*'], '', $aiReport);
        } else {
            $aiReport = "Relatório não gerado (Erro na API). HTTP: $httpCode. Curl: $curlError. Response: $response";
        }
    }

    // --- Email Sending ---
    $to = "betoorrico0205@gmail.com, henrick@itlmkt.com";
    $subject = "Resultado Teste Anti-Gororoba: $nome";

    $message = "
    <html>
    <head>
        <title>Resultado Teste Anti-Gororoba</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #8b5cf6; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; }
            .report { background-color: #f5f3ff; padding: 15px; border-left: 4px solid #8b5cf6; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>Novo Lead: Teste Anti-Gororoba</h2>
        </div>
        <div class='content'>
            <p><strong>Nome:</strong> $nome</p>
            <p><strong>E-mail:</strong> $email</p>
            <hr>
            <h3>Respostas</h3>
            <ul>
                <li>Cenário 1: {$answers['q1']}</li>
                <li>Cenário 2: {$answers['q2']}</li>
                <li>Cenário 3: {$answers['q3']}</li>
                <li>Cenário 4: {$answers['q4']}</li>
                <li>Cenário 5: {$answers['q5']}</li>
                <li>Cenário 6: {$answers['q6']}</li>
            </ul>
            <div class='report'>
                <h3>Análise da IA</h3>
                $aiReport
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@itlmkt.com" . "\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo json_encode([
            "status" => "success",
            "message" => "Teste enviado com sucesso!",
            "report" => $aiReport,
            "score" => $score,
            "correct_count" => $correctCount
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erro ao enviar e-mail."]);
    }

} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método não permitido."]);
}
?>