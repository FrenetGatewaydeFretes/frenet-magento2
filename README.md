# Frenet Gateway de Fretes para Magento 2

Integre sua loja Magento 2 aos serviços da [Frenet](https://www.frenet.com.br/) e ofereça cotação de frete em tempo real (Correios, transportadoras e Jadlog, entre outras) direto no carrinho, no checkout e na página de produto.

[![Packagist Version](https://img.shields.io/packagist/v/frenet/frenet-magento2)](https://packagist.org/packages/frenet/frenet-magento2)
![Packagist Downloads](https://img.shields.io/packagist/dt/frenet/frenet-magento2)
[![PHP](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-blue.svg)](http://www.php.net)
[![Magento](https://img.shields.io/badge/magento-2.4-orange.svg)](https://magento.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)

## Funcionalidades

- **Cotação de frete em tempo real** no carrinho e no checkout, via API da Frenet, com todas as transportadoras habilitadas na sua conta (Correios, Jadlog, transportadoras parceiras, etc).
- **Cotação de frete na página de produto**, antes de adicionar ao carrinho — configurável por tipo de produto (simples, configurável, bundle, agrupado).
- **Divisão automática em múltiplos pacotes** (*multi-quote*) quando o peso total do carrinho excede o peso máximo configurado por pacote (padrão 30kg), com cálculo de empacotamento O(1) independente da quantidade de itens.
- **Mapeamento de atributos de produto** para peso, altura, largura e comprimento — use os atributos padrão ou aponte para atributos customizados do seu catálogo.
- **Rastreio de encomendas** integrado (`Model/Tracking.php`), exibindo o status de entrega da Frenet direto no Magento.
- **Mensagem de previsão de entrega** configurável, com placeholder de prazo em dias (`{{d}}`).
- **Restrição por país e método de envio**, prazo adicional configurável, e opção de exibir ou ocultar métodos indisponíveis.
- **Log de depuração** opcional, para investigar requisições/respostas trocadas com a API da Frenet.
- **Limite de segurança configurável** para a quantidade de um mesmo item considerada no empacotamento (`Maximum Item Quantity per Shipping Line`), protegendo o cálculo de frete contra quantidades excessivas.
- **Unidade de peso do catálogo respeitada automaticamente**: o empacotamento lê `general/locale/weight_unit` da loja e converte para quilogramas quando o catálogo está em libras — sem configuração extra.
- **Hostname e protocolo da API configuráveis** (uso avançado), para apontar o módulo a um endpoint alternativo da Frenet quando o suporte orientar.
- Tradução para **pt-BR** incluída.

## Compatibilidade

| Módulo | Magento | PHP |
|---|---|---|
| `2.4.9` | 2.4.9 | 8.3, 8.4, 8.5 |
| `2.4.8-p5` | 2.4.8 / 2.4.8-p1 / 2.4.8-p2 / 2.4.8-p3 / 2.4.8-p4 / 2.4.8-p5 | 8.2, 8.3, 8.4 |
| `2.4.8-p4` | 2.4.8 / 2.4.8-p1 / 2.4.8-p2 / 2.4.8-p3 / 2.4.8-p4 | 8.2, 8.3, 8.4 |
| `2.4.8-p3` | 2.4.8 / 2.4.8-p1 / 2.4.8-p2 / 2.4.8-p3 | 8.2, 8.3, 8.4 |
| `2.4.8-p2` | 2.4.8 / 2.4.8-p1 / 2.4.8-p2 | 8.2, 8.3, 8.4 |
| `2.4.8-p1` | 2.4.8 / 2.4.8-p1 | 8.2, 8.3, 8.4 |

O `composer.json` do módulo declara as versões de `magento/framework` e dos módulos `magento/module-*` suportadas; o Composer resolve automaticamente a versão compatível com a sua instalação. Para versões anteriores do Magento (2.3.x, 2.4.0–2.4.7), utilize uma tag anterior do módulo (ex.: `2.4.7-p3`).

## Pré-requisitos

- Uma conta ativa na Frenet e um **token de API**, obtido em [painel.frenet.com.br](http://painel.frenet.com.br/).
- [Composer](https://getcomposer.org/) instalado no ambiente onde o Magento roda.

> É recomendado validar a instalação e qualquer atualização em um ambiente de testes antes de aplicar em produção.

## Instalação

Abra o terminal na raiz da sua instalação do Magento 2 e execute:

```bash
composer require frenet/frenet-magento2       # Baixa e requer o módulo
php bin/magento module:enable Frenet_Shipping # Ativa o módulo
php bin/magento setup:upgrade                 # Aplica os patches de setup do módulo
php bin/magento setup:di:compile              # Recompila o projeto (obrigatório em modo production)
php bin/magento cache:flush                   # Limpa o cache
```

Se você quiser fixar uma versão específica em vez do último release, informe a constraint diretamente:

```bash
composer require frenet/frenet-magento2:2.4.8-p5
```

## Configuração

No admin do Magento, acesse **Stores > Configuration > Sales > Delivery Methods > Frenet Shipping Gateway** e configure, no mínimo:

1. **Enabled**: ative o método.
2. **API Token**: cole o token obtido no painel da Frenet.
3. **Attributes Mapping**: confirme (ou ajuste) quais atributos do produto representam peso, altura, largura e comprimento.
4. **Default Measurements**: valores usados quando um produto não tem essas dimensões preenchidas.

Recursos opcionais, no mesmo painel:

- **Enable Multi Quote**: divide o carrinho em múltiplos pacotes quando o peso total ultrapassa o **Package Max Weight** (uma chamada de API por pacote gerado).
- **Package Max Weight (kg)**: peso máximo por pacote em quilogramas (padrão 30). Só precisa ser alterado se houver instrução específica.
- **Maximum Item Quantity per Shipping Line**: teto de segurança (padrão 5000) para a quantidade de um mesmo item considerada no cálculo de pacotes.
- **Product Quote**: habilita a cotação de frete diretamente na página de produto, antes do cliente adicionar ao carrinho.
- **Show Shipping Forecast** / **Shipping Forecast Message**: exibe uma mensagem de prazo estimado de entrega.
- **Debug**: grava as requisições/respostas da API da Frenet em `var/log/<Debug Filename>`, útil para diagnosticar problemas de cotação.
- **API Hostname** / **API Protocol**: sobrescrevem o endpoint da API da Frenet (protocolo padrão HTTPS). O hostname só aceita endereços `frenet.com.br`, como `api.frenet.com.br`. Só altere se o suporte da Frenet orientar.
- **API Timeout (seconds)**: tempo máximo de espera pela API da Frenet (padrão 30, máximo 120). Se a API demorar mais, a Frenet fica fora daquela cotação e o carrinho e o checkout seguem normalmente.

## Como funciona

- **Checkout/carrinho**: o Magento consulta `Frenet\Shipping\Model\Carrier\Frenet::collectRates()` durante a estimativa de frete, que monta os pacotes a partir dos itens do carrinho e consulta a API da Frenet, retornando as opções de envio disponíveis.
- **Página de produto**: se **Product Quote** estiver habilitado, o widget consulta o mesmo mecanismo de cotação para um único produto e quantidade informados, sem a necessidade de adicionar ao carrinho antes.
- **Rastreio**: pedidos com código de rastreio da Frenet exibem o status de entrega consultado via `getTracking()`.

## Suporte

- Dúvidas sobre a API/token da Frenet: [contato@frenet.com.br](mailto:contato@frenet.com.br) ou o [painel da Frenet](http://painel.frenet.com.br/).
- Bugs e sugestões neste módulo: abra uma [issue no GitHub](https://github.com/FrenetGatewaydeFretes/frenet-magento2/issues).

## Changelog

### 2.4.8-p5

**Adicionado**

- Compatibilidade com **Magento 2.4.8-p5** (PHP 8.2, 8.3 e 8.4), validada contra as correções de segurança do boletim APSB26-49: cotação na página do produto e no carrinho, checkout de visitante e de cliente logado, e envio com rastreio no admin. O módulo não precisou de alterações para esta versão.

### 2.4.8-p4

**Adicionado**

- Compatibilidade com **Magento 2.4.8-p4** (PHP 8.2, 8.3 e 8.4) e **Composer 2.9**, validada contra as correções de segurança do boletim APSB26-05, incluindo o checkout de cliente logado.
- Campo **API Timeout (seconds)**: tempo máximo de espera pela API da Frenet (padrão 30 segundos, máximo 120; a conexão desiste em até 10). Se a API demorar mais, a Frenet fica fora daquela cotação e o carrinho e o checkout seguem normalmente, em vez de travar.

**Corrigido**

- Com **Multi Quote** habilitado, carrinhos acima do peso máximo do pacote voltam a receber os fretes Frenet. Desde a 2.4.9 eles ficavam sem nenhuma opção. Os Correios são somados por pacote, e as demais transportadoras vêm da cotação do carrinho inteiro.
- Produtos com peso ou medida salvos como **0** passam a usar os valores padrão (**Default Measurements**), em vez de enviar 0 à Frenet, que deixava o carrinho sem cotação.
- O comentário do campo **API Protocol** volta a aparecer traduzido no admin em português.
- A cotação usa a configuração da loja do pedido (token, CEP de origem, medidas padrão e prazo de cada produto). Antes usava a loja aberta no momento, o que dava errado em pedidos criados pelo admin e em lojas com mais de um website.
- O cache de cotações passa a considerar tudo o que é enviado à Frenet: valor da nota, peso, medidas, cupom e a conta da loja. Antes, uma mudança de preço ou de peso, ou um website com outra conta Frenet, podia receber uma cotação antiga do cache.
- Itens filhos (por exemplo, a variação escolhida de um produto configurável) passam a enviar à Frenet as categorias do produto pai, onde o catálogo as cadastra.

**Alterado**

- Requer `frenet/frenet-php` **1.3.4**. A 1.3.3 passou a aplicar os limites de tempo nas chamadas à API, e a 1.3.4 grava cada chamada uma única vez no log de debug (antes as linhas se multiplicavam a cada chamada).
- A cotação lê cada atributo de produto (peso, dimensões, prazo) e as categorias uma única vez por requisição. Em carrinhos grandes isso reduz bastante as consultas ao banco: com 20 itens, de cerca de 880 para cerca de 510, e o tempo de banco de cerca de 230 ms para cerca de 60 ms. O frete calculado não muda.
- Com **Multi Quote**, pacotes idênticos são cotados uma única vez por requisição. Por exemplo, a cotação de 40 unidades de um item pesado na página do produto cai de 11 para 2 chamadas à API, e de cerca de 7 s para cerca de 1,5 s.

**Segurança**

- O campo **API Hostname** só aceita endereços `frenet.com.br` (por exemplo, `api.frenet.com.br`), sem protocolo, porta ou caminho. Outros valores são recusados ao salvar e, se chegarem por `env.php` ou `config.php`, são ignorados em favor do endereço padrão da Frenet. Assim o token nunca é enviado a outro servidor.

### 2.4.8-p3

**Adicionado**

- Compatibilidade com **Magento 2.4.8-p3** (PHP 8.2, 8.3 e 8.4), validada contra as correções de segurança do boletim APSB25-94, incluindo a estimativa de totais via GraphQL e a nova validação de parâmetros da API REST.

**Corrigido**

- Com **Show Shipping Forecast** desligado, os fretes Frenet voltam a aparecer no carrinho e no checkout.
- Os campos **Package Max Weight** e **Max Unit Quantity** salvos em branco voltam a usar os valores padrão (30 kg e 5000 unidades) em vez de zerar a cotação.
- A tela de envio no admin não oferece mais a opção **Create Shipping Label**, que a Frenet não gera.

**Segurança**

- A cotação na página do produto só responde quando o recurso está habilitado, o CEP tem 8 dígitos e o produto está ativo, à venda no website atual e com tipo permitido.
- Erros inesperados na cotação da página do produto são registrados no log e não exibem mais detalhes internos ao visitante.
- O arquivo de debug é sempre gravado em `var/log` e precisa terminar em `.log`.
- O token da Frenet é marcado como dado sensível e não é mais gravado no `app/etc/config.php` pelo `app:config:dump`.

**Alterado**

- O `composer.json` declara todos os módulos Magento usados pelo módulo, e o `module.xml` define a ordem de carga.

### 2.4.8-p2

**Adicionado**

- Compatibilidade com **Magento 2.4.8-p2** (PHP 8.2, 8.3 e 8.4), validada contra as correções de segurança do boletim APSB25-71.
- A linha 2.4.8 passa a usar a mesma base de código da 2.4.9: tudo o que está listado na seção **2.4.9** abaixo vale também para lojas em 2.4.8, incluindo os campos **API Hostname** / **API Protocol** (HTTPS por padrão) e as traduções pt-BR completas.

**Corrigido**

- Todas as correções listadas na seção **2.4.9**, em especial: carrinhos acima do peso máximo do pacote (padrão 30 kg) não retornam mais erro na estimativa de frete e no checkout.

### 2.4.9

**Adicionado**

- Compatibilidade com **Magento 2.4.9** e **PHP 8.5** (mantendo suporte a 8.3 e 8.4).
- Campos de configuração **API Hostname** e **API Protocol**, para apontar o módulo a um endpoint alternativo da Frenet quando o suporte orientar — protocolo HTTPS por padrão.
- Leitura automática da **unidade de peso do catálogo** (`general/locale/weight_unit`): o empacotamento converte para quilogramas quando a loja está configurada em libras, sem configuração extra.
- Traduções pt-BR completas em todos os campos de configuração.

**Corrigido**

- `collectRates()` não deixa mais uma falha ou indisponibilidade da API da Frenet escapar como exceção não tratada.
- Carrinhos que dividem em 2 ou mais pacotes (peso total acima do limite configurado) não quebram mais a cotação com um erro fatal — os preços de cada pacote são somados corretamente por método de envio.
- Com **Multi Quote** habilitado, o Correios não aparece mais duplicado (uma vez pela chamada cheia, outra pela soma dos pacotes) quando o carrinho é dividido.
- Itens de carrinho sem ID próprio (ex.: certas combinações de opções customizadas) agora são identificados de forma estável durante o empacotamento, em vez de colidirem entre si.
- A lista de tipos de produto elegíveis à cotação na página de produto não quebra mais quando a configuração está vazia.
- O cálculo de encaixe de pacotes agora soma o peso dos lotes sempre em quilogramas, evitando misturar unidades ao comparar com o limite configurado.
- Removido um aviso de performance ("JQueryUI Compat fallback") que o widget de cotação da página de produto disparava por depender de um módulo `jquery/ui` que não usava.
- Removida uma dependência da sessão de checkout durante a coleta de frete (`TotalsCollector`, `PackageProcessor`, `CouponProcessor`), eliminando um risco de erro `"Infinite loop detected"` do Magento core sob condições específicas de reentrância.

**Alterado**

- `Config`, `DeliveryTimeCalculator` e `RateRequestProvider` passam a ser injetados por interface (`ConfigInterface`, `DeliveryTimeCalculatorInterface`, `RateRequestProviderInterface`), facilitando substituição via `di.xml` em customizações.
- Construtores promovidos e PHPDoc padronizado nas classes do carrier e do empacotamento.
- `ModuleMetadata` passou a depender de uma factory de `Finder`, tornando a resolução da versão instalada testável.
- Metadados de versão obsoletos removidos (`setup_version` do `module.xml`, `<version>` do `config.xml`) — a versão do módulo é sempre lida do Composer.
- Suíte de testes unitários migrada para PHPUnit 12 e alinhada aos padrões internos de código.

## Desenvolvimento

O módulo segue o padrão de código `Magento2` (PHPCS) e PHPMD. Para rodar as verificações localmente:

```bash
composer coding-standard   # phpcs + phpmd
composer phpunit           # testes unitários
```

## Licença

Distribuído sob a licença [MIT](LICENSE.md).
