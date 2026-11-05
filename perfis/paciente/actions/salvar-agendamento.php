<?php
// perfis/paciente/actions/salvar-agendamento.php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../../auth/login.php");
    exit();
}

function voltarComErro(string $mensagem): void
{
    header('Location: ../agendar.php?erro=' . urlencode($mensagem));
    exit();
}

class ErroComprovanteAgendamento extends RuntimeException
{
}

function salvarComprovante(string $campo, int $pacienteId, bool $obrigatorio): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($obrigatorio) {
            throw new ErroComprovanteAgendamento('Anexe os documentos solicitados ou escolha apresentá-los na recepção.');
        }
        return null;
    }

    $arquivo = $_FILES[$campo];
    if ($arquivo['error'] !== UPLOAD_ERR_OK || $arquivo['size'] <= 0 || $arquivo['size'] > 5 * 1024 * 1024) {
        throw new ErroComprovanteAgendamento('Cada comprovante deve ter no máximo 5 MB e ser enviado corretamente.');
    }

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $mimesPermitidos = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    if (!isset($mimesPermitidos[$extensao])) {
        throw new ErroComprovanteAgendamento('Formato inválido. Envie PDF, JPG ou PNG.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    if (!in_array($mime, $mimesPermitidos[$extensao], true)) {
        throw new ErroComprovanteAgendamento('O conteúdo do arquivo não corresponde ao formato informado.');
    }

    $pastaDestino = dirname(__DIR__, 3) . '/uploads/documentos';
    if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true) && !is_dir($pastaDestino)) {
        throw new ErroComprovanteAgendamento('Não foi possível preparar a pasta para os documentos enviados.');
    }

    $nomeArquivo = 'doc_' . $pacienteId . '_' . bin2hex(random_bytes(12)) . '.' . $extensao;
    if (!move_uploaded_file($arquivo['tmp_name'], $pastaDestino . '/' . $nomeArquivo)) {
        throw new ErroComprovanteAgendamento('Não foi possível salvar o comprovante enviado.');
    }

    return 'uploads/documentos/' . $nomeArquivo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../agendar.php");
    exit();
}

$pacienteId = (int)$_SESSION['usuario_id'];
$tipoEscolhido = $_POST['tipo'] ?? '';
$tipoAtendimento = $tipoEscolhido === 'tele' ? 'Teleconsulta' : 'Presencial';
$medicoId = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
$especialidadeId = filter_input(INPUT_POST, 'especialidade_id', FILTER_VALIDATE_INT);
$dataConsulta = $_POST['data'] ?? '';
$horarioConsulta = $_POST['horario'] ?? '';
$modoDocumento = $_POST['modo_documento'] ?? 'agora';
$possuiConvenio = $_POST['possui_convenio'] ?? '';
$convenioId = filter_input(INPUT_POST, 'convenio_id', FILTER_VALIDATE_INT);
$planoId = filter_input(INPUT_POST, 'plano_id', FILTER_VALIDATE_INT);
$pacoteCode = $_POST['pacote'] ?? '';
$pacoteCheckup = null;

if (!in_array($tipoEscolhido, ['presencial', 'tele', 'checkup'], true)) {
    voltarComErro('Tipo de atendimento inválido.');
}
if ($tipoEscolhido === 'checkup') {
    $pacotesCheckupPermitidos = [
        'basico' => ['nome' => 'Básico', 'preco' => 180.00],
        'intermediario' => ['nome' => 'Intermediário', 'preco' => 320.00],
        'completo' => ['nome' => 'Completo', 'preco' => 550.00],
    ];
    if (!isset($pacotesCheckupPermitidos[$pacoteCode])) {
        voltarComErro('Selecione um pacote de Check-up válido.');
    }
    $pacoteCheckup = $pacotesCheckupPermitidos[$pacoteCode] + ['codigo' => $pacoteCode];
    try {
        $stmtEspecialidadeCheckup = $pdo->prepare("
            SELECT id
            FROM especialidade
            WHERE nome = ? AND status = 'Ativo'
            LIMIT 1
        ");
        $stmtEspecialidadeCheckup->execute(['Clínico Geral']);
        $especialidadeId = (int)$stmtEspecialidadeCheckup->fetchColumn();
    } catch (PDOException $e) {
        error_log('Erro ao localizar Clínica Geral para pacote de Check-up: ' . $e->getMessage());
        voltarComErro('Não foi possível validar a especialidade do pacote. Tente novamente.');
    }
    if ($especialidadeId < 1) {
        voltarComErro('O pacote de Check-up exige uma especialidade Clínica Geral ativa.');
    }
}
if (!$medicoId || !$especialidadeId) {
    voltarComErro('Selecione um médico e uma especialidade.');
}
if (!in_array($possuiConvenio, ['0', '1'], true)) {
    voltarComErro('Informe se usará convênio ou atendimento particular.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataConsulta) || !preg_match('/^\d{2}:\d{2}$/', $horarioConsulta)) {
    voltarComErro('Data ou horário inválido.');
}

$dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $dataConsulta);
$horaObj = DateTimeImmutable::createFromFormat('!H:i', $horarioConsulta);
if (!$dataObj || $dataObj->format('Y-m-d') !== $dataConsulta || !$horaObj || $horaObj->format('H:i') !== $horarioConsulta) {
    voltarComErro('Data ou horário inválido.');
}
if ($dataConsulta < date('Y-m-d') || ($dataConsulta === date('Y-m-d') && $horarioConsulta <= date('H:i'))) {
    voltarComErro('Escolha uma data e horário futuros.');
}
if (!in_array($modoDocumento, ['agora', 'recepcao'], true)) {
    voltarComErro('Opção de envio dos documentos inválida.');
}

$dataHora = $dataConsulta . ' ' . $horarioConsulta . ':00';
$arquivosSalvos = [];

try {
    $pdo->beginTransaction();

    // Confere novamente a disponibilidade do médico para impedir agendamento
    // com médico, horário ou especialidade adulterados ou já ocupados.
    $stmtMedico = $pdo->prepare("
        SELECT m.dias_trabalho, m.hora_inicio, m.hora_fim,
               COALESCE(m.duracao_consulta, 30) AS duracao_consulta,
               COALESCE(m.intervalo_consulta, 0) AS intervalo_consulta
        FROM medico m
        INNER JOIN medico_especialidades me ON me.id_medico = m.id
        WHERE m.id = ? AND me.id_especialidade = ? AND m.status = 'Ativo'
        LIMIT 1
        FOR UPDATE
    ");
    $stmtMedico->execute([$medicoId, $especialidadeId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        $pdo->rollBack();
        voltarComErro('O médico não está ativo ou não atende essa especialidade.');
    }

    $diasSemana = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diaSemana = $diasSemana[(int)$dataObj->format('N')];
    $diasTrabalho = array_map('trim', explode(',', (string)$medico['dias_trabalho']));
    $inicioConsulta = new DateTimeImmutable($dataHora);
    $fimConsulta = $inicioConsulta->modify('+' . (int)$medico['duracao_consulta'] . ' minutes');
    if (!in_array($diaSemana, $diasTrabalho, true)
        || $horarioConsulta < substr((string)$medico['hora_inicio'], 0, 5)
        || $fimConsulta->format('H:i:s') > (string)$medico['hora_fim']) {
        $pdo->rollBack();
        voltarComErro('O médico não atende nesse dia ou horário. Escolha outro horário.');
    }

    $stmtConflito = $pdo->prepare("
        SELECT COUNT(*) FROM consultas
        WHERE id_medico = ? AND data_hora = ? AND status <> 'cancelada'
    ");
    $stmtConflito->execute([$medicoId, $dataHora]);
    if ((int)$stmtConflito->fetchColumn() > 0) {
        $pdo->rollBack();
        voltarComErro('Esse horário acabou de ser reservado. Escolha outro horário.');
    }

    $stmtBloqueio = $pdo->prepare("
        SELECT COUNT(*) FROM bloqueio_agenda
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtBloqueio->execute([$medicoId, $dataHora]);
    if ((int)$stmtBloqueio->fetchColumn() > 0) {
        $pdo->rollBack();
        voltarComErro('O médico está indisponível nesse horário. Escolha outro.');
    }

    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*) FROM medico_ausencias
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtAusencia->execute([$medicoId, $dataConsulta]);
    if ((int)$stmtAusencia->fetchColumn() > 0) {
        $pdo->rollBack();
        voltarComErro('O médico está ausente nessa data. Escolha outro dia.');
    }

    $fimReserva = $inicioConsulta->modify(
        '+' . ((int)$medico['duracao_consulta'] + (int)$medico['intervalo_consulta']) . ' minutes'
    );
    $stmtBloqueioHora = $pdo->prepare("
        SELECT COUNT(*) FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
          AND hora_inicio < ? AND hora_fim > ?
    ");
    $stmtBloqueioHora->execute([
        $medicoId,
        $dataConsulta,
        $fimReserva->format('H:i:s'),
        $inicioConsulta->format('H:i:s'),
    ]);
    if ((int)$stmtBloqueioHora->fetchColumn() > 0) {
        $pdo->rollBack();
        voltarComErro('O médico está bloqueado nesse horário. Escolha outro.');
    }

    $stmtPaciente = $pdo->prepare("
        SELECT num_doc, tipo_documento, doc_comprovante, menor_idade,
               tipo_documento_resp, num_doc_resp, doc_comprovante_resp
        FROM paciente
        WHERE id = ?
        FOR UPDATE
    ");
    $stmtPaciente->execute([$pacienteId]);
    $paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);
    if (!$paciente) {
        $pdo->rollBack();
        voltarComErro('Cadastro do paciente não encontrado.');
    }

    // Cadastros da equipe já possuem documento principal. A etapa de documentos
    // só é aplicada quando faltam os dados básicos do paciente.
    $precisaDocumentoPaciente = empty($paciente['num_doc']) || empty($paciente['tipo_documento']);
    $menorIdade = $paciente['menor_idade'] === 'Y';
    $precisaDocumentoResponsavel = $menorIdade
        && (empty($paciente['tipo_documento_resp']) || empty($paciente['num_doc_resp']));
    $precisaDocumento = $precisaDocumentoPaciente || $precisaDocumentoResponsavel;
    if ($precisaDocumento) {
        $tipoDocumentoInformado = $precisaDocumentoPaciente ? trim($_POST['tipo_documento'] ?? '') : null;
        $numeroDocumentoInformado = $precisaDocumentoPaciente ? trim($_POST['numero_documento'] ?? '') : null;
        $endereco = trim($_POST['endereco'] ?? '');
        $tiposPermitidos = ['CPF', 'RG', 'CNH', 'Passaporte', 'Outro'];

        if ($precisaDocumentoPaciente
            && (!in_array($tipoDocumentoInformado, $tiposPermitidos, true) || $numeroDocumentoInformado === '')) {
            $pdo->rollBack();
            voltarComErro('Informe o tipo e o número do documento do paciente.');
        }

        $tipoDocumentoRespInformado = null;
        $numeroDocumentoRespInformado = null;
        if ($precisaDocumentoResponsavel) {
            $tipoDocumentoRespInformado = trim($_POST['tipo_documento_responsavel'] ?? '');
            $numeroDocumentoRespInformado = trim($_POST['numero_documento_responsavel'] ?? '');
            if (!in_array($tipoDocumentoRespInformado, $tiposPermitidos, true) || $numeroDocumentoRespInformado === '') {
                $pdo->rollBack();
                voltarComErro('Informe o tipo e o número do documento do responsável legal.');
            }
        }

        $caminhoPaciente = null;
        $caminhoResponsavel = null;
        $faltaComprovantePaciente = empty($paciente['doc_comprovante']);
        $faltaComprovanteResponsavel = $menorIdade && empty($paciente['doc_comprovante_resp']);
        $documentoPendente = $modoDocumento === 'recepcao'
            && ($faltaComprovantePaciente || $faltaComprovanteResponsavel) ? 1 : 0;
        if ($modoDocumento === 'agora') {
            $caminhoPaciente = salvarComprovante('foto_documento', $pacienteId, $faltaComprovantePaciente);
            if ($caminhoPaciente !== null) {
                $arquivosSalvos[] = $caminhoPaciente;
            }
            if ($faltaComprovanteResponsavel) {
                $caminhoResponsavel = salvarComprovante('foto_documento_responsavel', $pacienteId, true);
                if ($caminhoResponsavel !== null) {
                    $arquivosSalvos[] = $caminhoResponsavel;
                }
            }
        }

        $prazoDocumento = $documentoPendente ? $dataConsulta : null;
        $stmtAtualizaPaciente = $pdo->prepare("
            UPDATE paciente
            SET tipo_documento = COALESCE(NULLIF(?, ''), tipo_documento),
                num_doc = COALESCE(NULLIF(?, ''), num_doc),
                endereco = COALESCE(NULLIF(?, ''), endereco),
                doc_comprovante = COALESCE(?, doc_comprovante),
                tipo_documento_resp = COALESCE(NULLIF(?, ''), tipo_documento_resp),
                num_doc_resp = COALESCE(NULLIF(?, ''), num_doc_resp),
                doc_comprovante_resp = CASE WHEN menor_idade = 'Y' THEN COALESCE(?, doc_comprovante_resp) ELSE doc_comprovante_resp END,
                documento_pendente = ?,
                prazo_documento = ?
            WHERE id = ?
        ");
        $stmtAtualizaPaciente->execute([
            $tipoDocumentoInformado,
            $numeroDocumentoInformado,
            $endereco,
            $caminhoPaciente,
            $tipoDocumentoRespInformado,
            $numeroDocumentoRespInformado,
            $caminhoResponsavel,
            $documentoPendente,
            $prazoDocumento,
            $pacienteId,
        ]);
    }

    $nomePlano = null;
    if ($possuiConvenio === '1') {
        if (!$convenioId || $convenioId <= 0) {
            $pdo->rollBack();
            voltarComErro('Selecione um convênio ativo.');
        }

        $stmtConvenio = $pdo->prepare("SELECT id FROM convenios WHERE id = ? AND status = 'Ativo' LIMIT 1");
        $stmtConvenio->execute([$convenioId]);
        if (!$stmtConvenio->fetchColumn()) {
            $pdo->rollBack();
            voltarComErro('O convênio selecionado está inválido ou inativo.');
        }

        $stmtPlanos = $pdo->prepare("SELECT id, nome_plano FROM convenio_planos WHERE id_convenio = ? ORDER BY nome_plano");
        $stmtPlanos->execute([$convenioId]);
        $planos = $stmtPlanos->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($planos) {
            if (!$planoId || !array_key_exists($planoId, $planos)) {
                $pdo->rollBack();
                voltarComErro('Selecione um plano válido para o convênio escolhido.');
            }
            $nomePlano = $planos[$planoId];
        } else {
            $planoId = null;
        }
    } else {
        $convenioId = null;
        $planoId = null;
    }

    $stmtConsulta = $pdo->prepare("
        INSERT INTO consultas
            (id_paciente, id_medico, id_especialidade, data_hora, tipo_atendimento,
             agendado_por, status, id_convenio, id_plano, id_convenios)
        VALUES (?, ?, ?, ?, ?, 'Paciente', 'pendente', ?, ?, ?)
    ");
    $stmtConsulta->execute([
        $pacienteId,
        $medicoId,
        $especialidadeId,
        $dataHora,
        $tipoAtendimento,
        $convenioId,
        $planoId,
        $convenioId,
    ]);
    $consultaId = (int)$pdo->lastInsertId();

    if ($pacoteCheckup !== null) {
        $stmtPacoteCheckup = $pdo->prepare("
            INSERT INTO consulta_pacotes_checkup
                (id_consulta, codigo, nome, valor)
            VALUES (?, ?, ?, ?)
        ");
        $stmtPacoteCheckup->execute([
            $consultaId,
            $pacoteCheckup['codigo'],
            $pacoteCheckup['nome'],
            $pacoteCheckup['preco'],
        ]);
    }

    if ($convenioId !== null) {
        $pdo->prepare("
            UPDATE paciente
            SET id_convenio = ?, plano_convenio = ?, status_convenio = 'ativo'
            WHERE id = ?
        ")->execute([$convenioId, $nomePlano, $pacienteId]);
    }

    $pdo->commit();
    header("Location: ../painel.php?agendado=sucesso");
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($arquivosSalvos as $arquivo) {
        $caminho = dirname(__DIR__, 3) . '/' . $arquivo;
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }
    error_log('Erro ao salvar agendamento do paciente: ' . $e->getMessage());
    if ($e instanceof ErroComprovanteAgendamento) {
        voltarComErro($e->getMessage());
    }
    voltarComErro('Não foi possível concluir o agendamento. Verifique os dados e tente novamente.');
}
