# 📑 Specs de Correção do Plugin NFSe (WooCommerce)

## Contexto
Plugin de **WooCommerce (WordPress/PHP)** responsável por gerar e assinar **DPS** (Declaração de Prestação de Serviços) para o **projeto NFS-e Nacional**.  
O plugin está ~90% pronto, mas o **XML atual não valida no XSD oficial** da DPS.  

Serão disponibilizados:
- **XSDs oficiais disponíveis**: wp-content/plugins/woocommerce-cloudxm-nfse/schemas/xsd
- **XML de DPS inválida gerada pelo código atual**: wp-content/plugins/woocommerce-cloudxm-nfse/schemas/xml/dps_13.xml

---

## Objetivo
1. Corrigir o **gerador de XML da DPS** para aderir ao **`DPS_v1.00.xsd`**.  
2. Garantir a **assinatura digital** sobre o elemento correto (`infDPS`), no padrão **enveloped XMLDSig**.  
3. Entregar payload pronto (XML string ou Base64) para envio à **Produção Restrita** e Produção.  
4. Fornecer testes automáticos de validação XSD.

---

## Requisitos Funcionais (RF)
### Estrutura / Namespace
- **RF-01**: Raiz `<DPS>` com `xmlns="http://www.sped.fazenda.gov.br/nfse"`.  
- **RF-02**: Bloco principal deve ser `<infDPS Id="...">` (minúsculo).  
- **RF-03**: Atributo `Id` único, usado na assinatura (`Reference URI="#Id"`).  

### Identificação
- **RF-04**: Usar campos previstos no XSD: `nDPS`, `serie`, `dhEmi` (data/hora ISO8601), `dCompet` (data completa).  
- **RF-05**: Ambiente como `tpAmb` (1=Produção, 2=Homologação).  
- **RF-06**: Tipo de emissão `tpEmis`, processo `procEmi`.  

### Prestador e Tomador
- **RF-07**: Identificação com `<CNPJ>` ou `<CPF>`, nunca `tpInsc/nInsc`.  
- **RF-08**: Endereço em `<enderNac>` contendo: `xLgr`, `nro`, `xBairro`, `cMun` (IBGE 7 dígitos), `xMun`, `UF`, `CEP`.  
- **RF-09**: Telefone apenas dígitos.  
- **RF-10**: E-mail válido, texto puro.  

### Serviço
- **RF-11**: Campo `<xDescServ>` sem HTML.  
- **RF-12**: Valores: `vServ`, `vDescIncond`, `vDescCond`, `vDeducoes`.  
- **RF-13**: Grupo `<iss>` obrigatório quando houver ISS: `cMunInc`, `vBCISS`, `pAliq` (quatro casas decimais), `vISS` (duas casas decimais), `indISSRet`.  

### Assinatura Digital
- **RF-14**: Assinar `<infDPS>`.  
- **RF-15**: Assinatura **enveloped**, com `enveloped-signature` + `c14n`.  
- **RF-16**: Algoritmos: padrão **RSA-SHA256**; fallback para **SHA-1** se configurado.  
- **RF-17**: Em `<X509Data>`, incluir apenas o certificado do titular (`<X509Certificate>`).  

### Validação XSD 
- **RF-18**: Implementar validação automática contra `DPS_v1.00.xsd` usando `libxml` (PHP).  
- **RF-19**: na rotina de Emissão Manual (html-admin-manual-emission.php) incluir a demonstração da validação do xml gerado contra o XSD

### Payload
- **RF-20**: Retornar XML assinado em **string** ou em **Base64**, pronto para API.  
- **RF-21**: Escapar XML corretamente quando usado em JSON.

---

## Mapeamento (XML Atual → Padrão XSD)

| Campo atual | Correto no XSD | Observação |
|-------------|----------------|------------|
| `xmlns="http://www.nfse.gov.br/schema/dps_v1.xsd"` | `xmlns="http://www.sped.fazenda.gov.br/nfse"` | Corrigir namespace raiz |
| `<InfDPS>` | `<infDPS>` | Minúsculo |
| `identificacaoDps/numero` | `nDPS` | Numeração da DPS |
| `identificacaoDps/serie` | `serie` | Série da DPS |
| `identificacaoDps/dataEmissao` | `dhEmi` | Data/hora ISO8601 |
| `identificacaoDps/competencia (AAAA-MM)` | `dCompet (AAAA-MM-DD)` | Data completa |
| `identificacaoDps/ambienteId` | `tpAmb` | 1=Produção, 2=Homologação |
| `identificacaoDps/tipoEmissao` | `tpEmis` | Tipo emissão |
| `prest/tpInsc + prest/nInsc` | `emit/CNPJ` ou `emit/CPF` | Apenas um dos dois |
| `prest/end` | `emit/enderNac` | Incluir `xMun` |
| `prest/cont/fone` | `emit/fone` | Somente dígitos |
| `tom/tpInsc + tom/nInsc` | `tom/CNPJ` ou `tom/CPF` | Apenas um dos dois |
| `tom/end` | `tom/enderNac` | Incluir `xMun` |
| `serv/vTotTrib/*` | `vServ`, `vDescIncond`, `vDescCond`, `vDeducoes`, grupo `iss` | Separar por grupos corretos |
| `serv/xDescServ` (com HTML) | `xDescServ` texto puro | Remover HTML |

---

## Critérios de Aceite (DoD)
- [ ] XML gerado valida contra `DPS_v1.00.xsd`.  
- [ ] Assinatura aceita no Produção Restrita.  
- [ ] Telefone apenas dígitos.  
- [ ] Descrição sem HTML.  
- [ ] Função entrega XML string e Base64.

---

## Entregáveis
1. Spec de mapeamento e estrutura corrigida.  
2. Código PHP atualizado.

---

## Tarefas para o Kiro
1. Gerar tabela de mapeamento completo.  
2. Especificar a nova geração de XML conforme XSD.  
3. Especificar assinatura digital (alvo, algoritmos, transforms).  
4. Especificar validação automática com libxml.  
5. Especificar payload pronto (XML e Base64).  
6. Criar plano de testes (positivo/negativo).