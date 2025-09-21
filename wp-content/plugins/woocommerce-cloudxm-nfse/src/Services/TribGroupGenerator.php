<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use DOMDocument;
use DOMElement;

/**
 * Gerador do grupo <trib> para DPS
 * 
 * Gera apenas os campos obrigatórios conforme XSD TCInfoTributacao:
 * - tribMun (obrigatório)
 *   - tribISSQN (obrigatório)
 *   - tpRetISSQN (obrigatório)
 * - totTrib (obrigatório)
 *   - indTotTrib (obrigatório)
 */
class TribGroupGenerator
{
  /**
   * Adiciona o grupo <trib> ao elemento valores
   * 
   * @param DOMDocument $dom Documento XML
   * @param DOMElement $parent Elemento pai (valores)
   * @param array $data Dados do serviço
   */
  public function addTribGroup(DOMDocument $dom, DOMElement $parent, array $data): void
  {
    $trib = $dom->createElement('trib');
    $parent->appendChild($trib);

    // tribMun - Tributação municipal (ISSQN) - OBRIGATÓRIO
    $this->addTribMun($dom, $trib, $data);

    // totTrib - Total aproximado dos tributos - OBRIGATÓRIO
    $this->addTotTrib($dom, $trib);
  }

  /**
   * Adiciona o grupo tribMun (tributação municipal)
   * 
   * @param DOMDocument $dom Documento XML
   * @param DOMElement $parent Elemento trib
   * @param array $data Dados do serviço
   */
  private function addTribMun(DOMDocument $dom, DOMElement $parent, array $data): void
  {
    $tribMun = $dom->createElement('tribMun');
    $parent->appendChild($tribMun);

    // tribISSQN - Tributação do ISSQN - OBRIGATÓRIO
    $tribISSQN = $this->determineTribISSQN($data);
    $tribMun->appendChild($dom->createElement('tribISSQN', (string)$tribISSQN));

    // tpRetISSQN - Tipo de retenção do ISSQN - OBRIGATÓRIO
    $tpRetISSQN = $this->determineTpRetISSQN($data);
    $tribMun->appendChild($dom->createElement('tpRetISSQN', (string)$tpRetISSQN));
  }

  /**
   * Adiciona o grupo totTrib (total dos tributos)
   * 
   * @param DOMDocument $dom Documento XML
   * @param DOMElement $parent Elemento trib
   */
  private function addTotTrib(DOMDocument $dom, DOMElement $parent): void
  {
    $totTrib = $dom->createElement('totTrib');
    $parent->appendChild($totTrib);

    // indTotTrib - Indicador para não informar valores estimados - OBRIGATÓRIO
    // Valor fixo 0 conforme Decreto 8.264/2014
    $totTrib->appendChild($dom->createElement('indTotTrib', '0'));
  }

  /**
   * Determina o tipo de tributação do ISSQN
   * 
   * @param array $data Dados do serviço
   * @return int Código da tributação ISSQN
   */
  private function determineTribISSQN(array $data): int
  {
    // Verificar se é exportação de serviço (país diferente do Brasil)
    if (!empty($data['codigo_pais']) && $data['codigo_pais'] !== 'BR') {
      return 2; // Exportação de serviço
    }

    // Verificar se há configuração específica de tributação
    if (!empty($data['tipo_tributacao_issqn'])) {
      return (int)$data['tipo_tributacao_issqn'];
    }

    // Padrão: operação tributável
    return 1; // Operação tributável
  }

  /**
   * Determina o tipo de retenção do ISSQN
   * 
   * @param array $data Dados do serviço
   * @return int Código do tipo de retenção
   */
  private function determineTpRetISSQN(array $data): int
  {
    // Verificar se há retenção configurada
    if (!empty($data['iss_retido']) && $data['iss_retido'] === true) {
      return 2; // Retido pelo Tomador
    }

    // Verificar se há retenção por intermediário
    if (!empty($data['iss_retido_intermediario']) && $data['iss_retido_intermediario'] === true) {
      return 3; // Retido pelo Intermediário
    }

    // Padrão: não retido
    return 1; // Não Retido
  }

  /**
   * Valida os dados necessários para geração do grupo trib
   * 
   * @param array $data Dados do serviço
   * @return array Array com resultado da validação
   */
  public function validateTribData(array $data): array
  {
    $errors = [];
    $warnings = [];

    // Validar tribISSQN
    $tribISSQN = $this->determineTribISSQN($data);
    if (!in_array($tribISSQN, [1, 2, 3, 4])) {
      $errors[] = "Tipo de tributação ISSQN inválido: $tribISSQN. Deve ser 1, 2, 3 ou 4.";
    }

    // Validar tpRetISSQN
    $tpRetISSQN = $this->determineTpRetISSQN($data);
    if (!in_array($tpRetISSQN, [1, 2, 3])) {
      $errors[] = "Tipo de retenção ISSQN inválido: $tpRetISSQN. Deve ser 1, 2 ou 3.";
    }

    // Avisos para campos opcionais que poderiam ser incluídos
    if ($tribISSQN === 2 && empty($data['codigo_pais'])) {
      $warnings[] = "Para exportação de serviço, recomenda-se informar o código do país de destino.";
    }

    if ($tribISSQN === 1 && empty($data['aliquota_issqn'])) {
      $warnings[] = "Para operação tributável, a alíquota ISSQN pode ser informada se conhecida.";
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'warnings' => $warnings
    ];
  }

  /**
   * Gera dados de exemplo para teste
   * 
   * @return array Dados de exemplo
   */
  public function getExampleData(): array
  {
    return [
      'codigo_pais' => 'BR', // Brasil
      'tipo_tributacao_issqn' => 1, // Operação tributável
      'iss_retido' => false, // Não retido
      'iss_retido_intermediario' => false, // Não retido por intermediário
      'aliquota_issqn' => null // Não informada (será fornecida pelo sistema)
    ];
  }
}
