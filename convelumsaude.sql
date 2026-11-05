-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           8.0.30 - MySQL Community Server - GPL
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para convelumsaude
CREATE DATABASE IF NOT EXISTS `convelumsaude` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `convelumsaude`;

-- Copiando estrutura para tabela convelumsaude.atendimento
CREATE TABLE IF NOT EXISTS `atendimento` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_consulta` bigint unsigned NOT NULL,
  `id_prontuario` bigint unsigned NOT NULL,
  `queixa_principal` text NOT NULL,
  `exame_fisico` text,
  `diagnostico` text NOT NULL,
  `evolucao_clinica` text NOT NULL,
  `conduta` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_atendimento_consultas` (`id_consulta`),
  KEY `FK_atendimento_prontuario` (`id_prontuario`),
  CONSTRAINT `FK_atendimento_consultas` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_atendimento_prontuario` FOREIGN KEY (`id_prontuario`) REFERENCES `prontuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.atendimento: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.atendimento_telefonico
CREATE TABLE IF NOT EXISTS `atendimento_telefonico` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `paciente` varchar(200) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `assunto` varchar(50) DEFAULT NULL,
  `resumo` text NOT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.atendimento_telefonico: ~1 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.atestados
CREATE TABLE IF NOT EXISTS `atestados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_paciente` bigint unsigned NOT NULL,
  `id_medico` bigint unsigned DEFAULT NULL,
  `tipo` varchar(150) NOT NULL DEFAULT 'Afastamento do trabalho/estudo',
  `data_emissao` date NOT NULL,
  `periodo` varchar(150) NOT NULL,
  `cid` varchar(100) DEFAULT NULL,
  `observacoes` text,
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_atestados_paciente` (`id_paciente`),
  KEY `FK_atestados_medico` (`id_medico`),
  CONSTRAINT `FK_atestados_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_atestados_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.atestados: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.autorizacoes
CREATE TABLE IF NOT EXISTS `autorizacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `procedimento` varchar(255) NOT NULL,
  `medico` varchar(255) NOT NULL,
  `data_pedido` date DEFAULT NULL,
  `justificativa` text,
  `arquivo` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pendente',
  `data_solicitacao` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.autorizacoes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.bloqueios
CREATE TABLE IF NOT EXISTS `bloqueios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_alvo` varchar(50) NOT NULL,
  `alvo_id` varchar(50) NOT NULL,
  `nome_alvo` varchar(255) NOT NULL,
  `motivo` text NOT NULL,
  `data_inicio` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `data_fim` date NOT NULL,
  `hora_fim` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.bloqueios: ~4 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.bloqueio_agenda
CREATE TABLE IF NOT EXISTS `bloqueio_agenda` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_medico` bigint unsigned DEFAULT NULL,
  `id_sala` bigint unsigned DEFAULT NULL,
  `motivo` text NOT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_bloqueio_medico` (`id_medico`),
  KEY `FK_bloqueio_sala` (`id_sala`),
  CONSTRAINT `FK_bloqueio_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_bloqueio_sala` FOREIGN KEY (`id_sala`) REFERENCES `salas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.bloqueio_agenda: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.consultas
CREATE TABLE IF NOT EXISTS `consultas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_paciente` bigint unsigned NOT NULL,
  `id_medico` bigint unsigned NOT NULL,
  `id_especialidade` bigint unsigned NOT NULL,
  `id_sala` bigint unsigned DEFAULT NULL,
  `data_hora` datetime NOT NULL,
  `tipo_atendimento` enum('Presencial','Teleconsulta') NOT NULL DEFAULT 'Presencial',
  `agendado_por` enum('Recepcionista','Paciente','Médico') NOT NULL DEFAULT 'Recepcionista',
  `status` enum('pendente','confirmada','cancelada','realizada') NOT NULL DEFAULT 'pendente',
  `is_encaixe` tinyint(1) NOT NULL DEFAULT '0',
  `id_convenio` bigint unsigned DEFAULT NULL,
  `id_plano` bigint unsigned DEFAULT NULL,
  `id_convenios` bigint unsigned DEFAULT NULL,
  `checkin_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_consultas_paciente` (`id_paciente`),
  KEY `FK_consultas_medico` (`id_medico`),
  KEY `FK_consultas_especialidade` (`id_especialidade`),
  KEY `FK_consultas_salas` (`id_sala`),
  KEY `FK_consultas_convenios` (`id_convenios`),
  KEY `FK_consultas_plano` (`id_plano`),
  CONSTRAINT `FK_consultas_convenios` FOREIGN KEY (`id_convenios`) REFERENCES `convenios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_consultas_especialidade` FOREIGN KEY (`id_especialidade`) REFERENCES `especialidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_consultas_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_consultas_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_consultas_plano` FOREIGN KEY (`id_plano`) REFERENCES `convenio_planos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_consultas_salas` FOREIGN KEY (`id_sala`) REFERENCES `salas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.consultas: ~2 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.convenios
CREATE TABLE IF NOT EXISTS `convenios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `registro_ans` varchar(50) DEFAULT NULL,
  `prazo_faturamento` varchar(50) DEFAULT NULL,
  `telefone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.convenios: ~3 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.convenio_paciente
CREATE TABLE IF NOT EXISTS `convenio_paciente` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_paciente` bigint unsigned NOT NULL,
  `nome` varchar(255) NOT NULL,
  `plano` varchar(255) NOT NULL,
  `carteirinha` varchar(100) NOT NULL,
  `validade` varchar(20) NOT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_convenio_paciente` (`id_paciente`),
  CONSTRAINT `FK_convenio_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.convenio_paciente: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.convenio_planos
CREATE TABLE IF NOT EXISTS `convenio_planos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_convenio` bigint unsigned NOT NULL,
  `nome_plano` varchar(100) NOT NULL,
  `cobertura` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_planos_convenio` (`id_convenio`),
  CONSTRAINT `FK_planos_convenio` FOREIGN KEY (`id_convenio`) REFERENCES `convenios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.convenio_planos: ~3 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.despesas
CREATE TABLE IF NOT EXISTS `despesas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `status` enum('Pendente','Pago','Cancelado') NOT NULL DEFAULT 'Pendente',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.despesas: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.encaixes
CREATE TABLE IF NOT EXISTS `encaixes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `paciente` varchar(200) NOT NULL,
  `medico` varchar(150) NOT NULL,
  `data_encaixe` date NOT NULL,
  `horario` varchar(10) NOT NULL,
  `tipo_atendimento` varchar(50) DEFAULT 'presencial',
  `motivo` text,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pendente','confirmada','cancelada','realizada') NOT NULL DEFAULT 'pendente',
  `checkin_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.encaixes: ~1 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.especialidade
CREATE TABLE IF NOT EXISTS `especialidade` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `preco` decimal(10,2) DEFAULT NULL,
  `icone` varchar(255) DEFAULT NULL,
  `descricao` text,
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.especialidade: ~4 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.exames
CREATE TABLE IF NOT EXISTS `exames` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_paciente` bigint unsigned NOT NULL,
  `id_medico` bigint unsigned DEFAULT NULL,
  `nome_exame` varchar(255) NOT NULL,
  `urgencia` varchar(20) DEFAULT 'Rotina',
  `preparo` varchar(50) DEFAULT NULL,
  `justificativa` varchar(255) DEFAULT NULL,
  `observacoes` text,
  `data_exame` date NOT NULL,
  `status` enum('disponivel','pendente') NOT NULL DEFAULT 'pendente',
  `arquivo_laudo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_exames_paciente` (`id_paciente`),
  KEY `FK_exames_medico` (`id_medico`),
  CONSTRAINT `FK_exames_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_exames_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.exames: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.feriados
CREATE TABLE IF NOT EXISTS `feriados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `data` date NOT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'Nacional',
  `recorrente` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.feriados: ~5 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.gestores
CREATE TABLE IF NOT EXISTS `gestores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cpf` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(15) DEFAULT NULL,
  `celular` varchar(15) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `cargo` varchar(50) DEFAULT 'Gestor Geral',
  `nivel_acesso` tinyint unsigned NOT NULL DEFAULT '4',
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.gestores: ~1 rows (aproximadamente)
INSERT INTO `gestores` (`id`, `uid`, `nome`, `cpf`, `email`, `senha`, `telefone`, `celular`, `foto`, `cargo`, `nivel_acesso`, `status`, `primeiro_acesso`) VALUES
	(1, 'GEST001', 'Administrador Inicial', '000.000.000-00', 'admin@convelumsaude.com', '$2y$10$vSTX9uzhslR5ldBMMN4zYeEHgeEr4CUa/LPyYNpxVEENmegwUDB1a', '(00) 0000-0000', '(00) 00000-0000', NULL, 'Gestor Geral', 4, 'Ativo', 0);

-- Copiando estrutura para tabela convelumsaude.medico
CREATE TABLE IF NOT EXISTS `medico` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `crm` varchar(20) NOT NULL,
  `cpf` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `celular` varchar(15) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `nivel_acesso` tinyint unsigned NOT NULL DEFAULT '2',
  `dias_trabalho` varchar(100) DEFAULT 'seg,ter,qua,qui,sex',
  `hora_inicio` time DEFAULT '08:00:00',
  `hora_fim` time DEFAULT '18:00:00',
  `duracao_consulta` int DEFAULT '30',
  `intervalo_consulta` int DEFAULT '10',
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.medico: ~2 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.medico_ausencias
CREATE TABLE IF NOT EXISTS `medico_ausencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_medico` int NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.medico_ausencias: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.medico_bloqueios
CREATE TABLE IF NOT EXISTS `medico_bloqueios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_medico` int NOT NULL,
  `data_bloqueio` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.medico_bloqueios: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.medico_especialidades
CREATE TABLE IF NOT EXISTS `medico_especialidades` (
  `id_medico` bigint unsigned NOT NULL,
  `id_especialidade` bigint unsigned NOT NULL,
  PRIMARY KEY (`id_medico`,`id_especialidade`),
  KEY `FK_med_esp_especialidade` (`id_especialidade`),
  CONSTRAINT `FK_med_esp_especialidade` FOREIGN KEY (`id_especialidade`) REFERENCES `especialidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_med_esp_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.medico_especialidades: ~2 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.paciente
CREATE TABLE IF NOT EXISTS `paciente` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) NOT NULL,
  `id_convenio` bigint unsigned DEFAULT NULL,
  `plano_convenio` varchar(100) DEFAULT NULL,
  `num_carteirinha` varchar(50) DEFAULT NULL,
  `validade_carteirinha` date DEFAULT NULL,
  `status_convenio` varchar(20) DEFAULT 'inativo',
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  `nome` varchar(200) NOT NULL,
  `menor_idade` enum('Y','N') NOT NULL DEFAULT 'N',
  `tipo_documento` varchar(20) DEFAULT NULL,
  `num_doc` varchar(60) DEFAULT NULL,
  `doc_comprovante` varchar(255) DEFAULT NULL,
  `doc_comprovante_resp` varchar(255) DEFAULT NULL,
  `data_nasc` date NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(15) NOT NULL,
  `endereco` varchar(200) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome_resp` varchar(100) DEFAULT NULL,
  `tipo_documento_resp` varchar(20) DEFAULT NULL,
  `data_nasc_resp` date DEFAULT NULL,
  `num_doc_resp` varchar(30) DEFAULT NULL,
  `telefone_resp` varchar(15) DEFAULT NULL,
  `celular_resp` varchar(15) DEFAULT NULL,
  `email_resp` varchar(100) DEFAULT NULL,
  `obs_paciente` text,
  `nivel_acesso` tinyint unsigned NOT NULL DEFAULT '1',
  `documento_pendente` tinyint(1) NOT NULL DEFAULT '0',
  `prazo_documento` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `FK_paciente_convenios` (`id_convenio`),
  CONSTRAINT `FK_paciente_convenios` FOREIGN KEY (`id_convenio`) REFERENCES `convenios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.paciente: ~4 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.prontuario
CREATE TABLE IF NOT EXISTS `prontuario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_paciente` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_prontuario_paciente` (`id_paciente`),
  CONSTRAINT `FK_prontuario_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.prontuario: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.receitas
CREATE TABLE IF NOT EXISTS `receitas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_paciente` bigint unsigned NOT NULL,
  `id_medico` bigint unsigned DEFAULT NULL,
  `tipo` varchar(100) NOT NULL DEFAULT 'Receita comum',
  `observacoes` text,
  `data_emissao` date NOT NULL,
  `status` enum('ativa','expirada') NOT NULL DEFAULT 'ativa',
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_receitas_paciente` (`id_paciente`),
  KEY `FK_receitas_medico` (`id_medico`),
  CONSTRAINT `FK_receitas_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_receitas_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.receitas: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.receita_medicamentos
CREATE TABLE IF NOT EXISTS `receita_medicamentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_receita` bigint unsigned NOT NULL,
  `nome` varchar(255) NOT NULL,
  `posologia` varchar(255) NOT NULL,
  `duracao` varchar(100) NOT NULL,
  `via` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_medicamentos_receita` (`id_receita`),
  CONSTRAINT `FK_medicamentos_receita` FOREIGN KEY (`id_receita`) REFERENCES `receitas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.receita_medicamentos: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.recepcionistas
CREATE TABLE IF NOT EXISTS `recepcionistas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cpf` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `celular` varchar(15) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `nivel_acesso` tinyint unsigned NOT NULL DEFAULT '3',
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.recepcionistas: ~1 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.rede_credenciada
CREATE TABLE IF NOT EXISTS `rede_credenciada` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `especialidade` varchar(255) NOT NULL,
  `cidade` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.rede_credenciada: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.salas
CREATE TABLE IF NOT EXISTS `salas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `id_medico` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Disponivel',
  PRIMARY KEY (`id`),
  KEY `FK_salas_medico` (`id_medico`),
  CONSTRAINT `FK_salas_medico` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.salas: ~2 rows (aproximadamente)

-- Copiando estrutura para tabela convelumsaude.solicitacoes_reset
CREATE TABLE IF NOT EXISTS `solicitacoes_reset` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `tipo_usuario` enum('Medico','Recepcionista','Gestor','Paciente') NOT NULL,
  `token` varchar(255) NOT NULL,
  `status` enum('Pendente','Aprovado','Expirado') NOT NULL DEFAULT 'Pendente',
  `data_solicitacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela convelumsaude.solicitacoes_reset: ~0 rows (aproximadamente)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
