<?php
// politica-privacidade.php
// Página estática de Política de Privacidade — ajuste os dados da clínica
// (nome, CNPJ, endereço, e-mail do DPO) nos campos indicados como [TODO].
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - Convelum Saúde</title>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsivo.css">

    <style>
        .privacidade-container {
            max-width: 860px;
            margin: 0 auto;
            padding: 40px 20px 80px;
            line-height: 1.7;
            color: #334155;
        }
        .privacidade-container h1 {
            font-size: 28px;
            margin-bottom: 4px;
        }
        .privacidade-atualizado {
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 32px;
        }
        .privacidade-container h2 {
            font-size: 19px;
            margin-top: 32px;
            margin-bottom: 10px;
            color: #1e293b;
        }
        .privacidade-container p,
        .privacidade-container li {
            font-size: 15px;
        }
        .privacidade-container ul {
            padding-left: 22px;
            margin-bottom: 12px;
        }
        .privacidade-container ul li {
            margin-bottom: 6px;
        }
        .privacidade-destaque {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 14px 18px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 14px;
        }
        .privacidade-voltar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 24px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
        }
        .privacidade-voltar:hover {
            text-decoration: underline;
        }
        .privacidade-contato {
            background: #f8fafc;
            border-radius: 8px;
            padding: 18px 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<!-- ========================= TOPO ========================= -->
<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="index.php">
            <img src="imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>
</div>

<div class="privacidade-container">

    <a href="javascript:history.back()" class="privacidade-voltar">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>

    <h1>Política de Privacidade</h1>
    <p class="privacidade-atualizado">Última atualização: <?php echo date('d/m/Y'); ?></p>

    <p>
        A <strong>Convelum Saúde</strong> [TODO: nome jurídico completo / CNPJ] valoriza a
        privacidade e a proteção dos dados pessoais de seus pacientes, médicos, gestores e
        colaboradores. Esta Política de Privacidade explica como coletamos, usamos,
        armazenamos e protegemos os dados tratados por meio do nosso sistema, em conformidade
        com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018 — LGPD).
    </p>

    <div class="privacidade-destaque">
        <i class="bi bi-shield-lock"></i>
        Por lidarmos com <strong>dados de saúde</strong>, tratados pela LGPD como dado pessoal
        sensível, adotamos cuidados adicionais de segurança e restringimos o acesso a essas
        informações apenas às pessoas estritamente necessárias para a prestação do atendimento.
    </div>

    <h2>1. Quais dados coletamos</h2>
    <p>Dependendo do seu perfil de acesso, podemos coletar:</p>
    <ul>
        <li><strong>Pacientes:</strong> nome completo, CPF/RG/documento equivalente, data de nascimento, e-mail, telefone/celular, endereço, dados de responsável legal (quando menor de idade), histórico de consultas, prontuário médico, receitas, exames e atestados.</li>
        <li><strong>Médicos:</strong> nome, CPF, CRM, especialidades, dados de contato e dados profissionais necessários ao exercício da atividade.</li>
        <li><strong>Gestores e recepcionistas:</strong> nome, CPF, e-mail, telefone/celular e dados funcionais de acesso ao sistema.</li>
        <li><strong>Dados de acesso:</strong> UID de acesso, registros de login e uso do sistema, para fins de segurança e auditoria.</li>
    </ul>

    <h2>2. Para que usamos seus dados</h2>
    <ul>
        <li>Viabilizar o agendamento e a realização de consultas, exames e demais atendimentos;</li>
        <li>Manter o prontuário médico e o histórico clínico do paciente;</li>
        <li>Emitir receitas, atestados e demais documentos médicos;</li>
        <li>Gerenciar o acesso de médicos, gestores, recepcionistas e pacientes ao sistema;</li>
        <li>Cumprir obrigações legais e regulatórias aplicáveis à área da saúde (ex: Conselho Federal de Medicina, ANVISA);</li>
        <li>Enviar comunicações operacionais, como confirmação de cadastro e instruções de primeiro acesso.</li>
    </ul>

    <h2>3. Com quem compartilhamos seus dados</h2>
    <p>
        Seus dados podem ser compartilhados, quando necessário e dentro dos limites legais, com:
    </p>
    <ul>
        <li>Profissionais de saúde diretamente envolvidos no seu atendimento;</li>
        <li>Convênios e operadoras de saúde [TODO: se aplicável];</li>
        <li>Autoridades públicas, mediante obrigação legal ou ordem judicial;</li>
        <li>Prestadores de serviços de tecnologia que auxiliam na operação do sistema, sob obrigação contratual de confidencialidade.</li>
    </ul>
    <p>Não vendemos nem compartilhamos seus dados com terceiros para fins de publicidade.</p>

    <h2>4. Por quanto tempo guardamos seus dados</h2>
    <p>
        O prontuário médico e os dados clínicos são mantidos pelo prazo mínimo exigido pela
        legislação e pelas normas dos conselhos profissionais de saúde [TODO: confirmar prazo,
        geralmente 20 anos conforme resolução do CFM]. Dados cadastrais de usuários inativados
        são mantidos para preservar o histórico clínico, mesmo após a perda de acesso ao sistema.
    </p>

    <h2>5. Como protegemos seus dados</h2>
    <ul>
        <li>Senhas armazenadas de forma criptografada, nunca em texto puro;</li>
        <li>Controle de acesso por perfil (paciente, médico, recepcionista, gestor), cada um vendo apenas o que é necessário;</li>
        <li>Exigência de troca de senha no primeiro acesso ao sistema;</li>
        <li>Registro de tentativas de login e monitoramento de acessos indevidos.</li>
    </ul>

    <h2>6. Seus direitos como titular de dados</h2>
    <p>Conforme a LGPD, você pode solicitar a qualquer momento:</p>
    <ul>
        <li>Confirmação da existência de tratamento dos seus dados;</li>
        <li>Acesso, correção ou atualização dos seus dados cadastrais;</li>
        <li>Anonimização, bloqueio ou eliminação de dados desnecessários ou tratados em desconformidade com a lei;</li>
        <li>Portabilidade dos dados, observadas as exceções legais aplicáveis a dados de saúde;</li>
        <li>Informação sobre com quem seus dados foram compartilhados.</li>
    </ul>
    <p>
        Alguns dados clínicos podem ter sua eliminação restrita por obrigação legal de guarda de
        prontuário, mesmo diante de uma solicitação de exclusão.
    </p>

    <h2>7. Cookies</h2>
    <p>
        Utilizamos cookies essenciais para manter sua sessão ativa e, quando você escolhe a
        opção "Lembrar senha", para facilitar seu próximo login. Você pode gerenciar cookies
        nas configurações do seu navegador.
    </p>

    <h2>8. Alterações nesta política</h2>
    <p>
        Esta Política de Privacidade pode ser atualizada periodicamente. Recomendamos que você
        a revise de tempos em tempos. A data da última atualização está indicada no topo desta
        página.
    </p>

    <h2>9. Fale com o nosso Encarregado de Dados (DPO)</h2>
    <div class="privacidade-contato">
        <p>
            Em caso de dúvidas sobre esta Política de Privacidade ou para exercer seus direitos
            como titular de dados, entre em contato:
        </p>
        <p>
            <i class="bi bi-envelope"></i> E-mail: <strong>contato@convelumsaude.com.br</strong><br>
            <i class="bi bi-telephone"></i> Telefone: <strong>(11) 1234-5678</strong><br>
            <i class="bi bi-geo-alt"></i> Endereço: <strong>Av. Brigadeiro Luís Antônio, 234 – Centro, São Paulo - SP</strong>
        </p>
    </div>

</div>

</body>
</html>