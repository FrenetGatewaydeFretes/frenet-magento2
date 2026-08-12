# Frenet Gateway de Fretes para Magento 2

Integre sua loja Magento 2 aos serviços da [Frenet](https://www.frenet.com.br/) e ofereça cotação de frete em tempo real (Correios, transportadoras e Jadlog, entre outras) direto no carrinho, no checkout e na página de produto.

[![Packagist Version](https://img.shields.io/packagist/v/frenet/frenet-magento2)](https://packagist.org/packages/frenet/frenet-magento2)
![Packagist Downloads](https://img.shields.io/packagist/dt/frenet/frenet-magento2)
[![PHP](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4-blue.svg)](http://www.php.net)
[![Magento](https://img.shields.io/badge/magento-2.4-orange.svg)](https://magento.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)

## Funcionalidades

- **Cotação de frete em tempo real** no carrinho e no checkout, via API da Frenet, com todas as transportadoras habilitadas na sua conta (Correios, Jadlog, transportadoras parceiras, etc).
- **Cotação de frete na página de produto**, antes de adicionar ao carrinho — configurável por tipo de produto (simples, configurável, bundle, agrupado).
- **Divisão automática em múltiplos pacotes** (*multi-quote*) quando o peso total do carrinho excede o limite de um único pacote (30kg), com cálculo de empacotamento O(1) independente da quantidade de itens.
- **Mapeamento de atributos de produto** para peso, altura, largura e comprimento — use os atributos padrão ou aponte para atributos customizados do seu catálogo.
- **Rastreio de encomendas** integrado (`Model/Tracking.php`), exibindo o status de entrega da Frenet direto no Magento.
- **Mensagem de previsão de entrega** configurável, com placeholder de prazo em dias (`{{d}}`).
- **Restrição por país e método de envio**, prazo adicional configurável, e opção de exibir ou ocultar métodos indisponíveis.
- **Log de depuração** opcional, para investigar requisições/respostas trocadas com a API da Frenet.
- **Limite de segurança configurável** para a quantidade de um mesmo item considerada no empacotamento (`Maximum Item Quantity per Shipping Line`), protegendo o cálculo de frete contra quantidades excessivas.
- Tradução para **pt-BR** incluída.

## Compatibilidade

| Módulo | Magento | PHP |
|---|---|---|
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
composer require frenet/frenet-magento2:^2.4.8-p1
```

## Configuração

No admin do Magento, acesse **Stores > Configuration > Sales > Delivery Methods > Frenet Shipping Gateway** e configure, no mínimo:

1. **Enabled**: ative o método.
2. **API Token**: cole o token obtido no painel da Frenet.
3. **Attributes Mapping**: confirme (ou ajuste) quais atributos do produto representam peso, altura, largura e comprimento.
4. **Default Measurements**: valores usados quando um produto não tem essas dimensões preenchidas.

Recursos opcionais, no mesmo painel:

- **Enable Multi Quote**: divide o carrinho em múltiplos pacotes quando o peso total ultrapassa 30kg (uma chamada de API por pacote gerado).
- **Maximum Item Quantity per Shipping Line**: teto de segurança (padrão 5000) para a quantidade de um mesmo item considerada no cálculo de pacotes.
- **Product Quote**: habilita a cotação de frete diretamente na página de produto, antes do cliente adicionar ao carrinho.
- **Show Shipping Forecast** / **Shipping Forecast Message**: exibe uma mensagem de prazo estimado de entrega.
- **Debug**: grava as requisições/respostas da API da Frenet em `var/log/<Debug Filename>`, útil para diagnosticar problemas de cotação.

## Como funciona

- **Checkout/carrinho**: o Magento consulta `Frenet\Shipping\Model\Carrier\Frenet::collectRates()` durante a estimativa de frete, que monta os pacotes a partir dos itens do carrinho e consulta a API da Frenet, retornando as opções de envio disponíveis.
- **Página de produto**: se **Product Quote** estiver habilitado, o widget consulta o mesmo mecanismo de cotação para um único produto e quantidade informados, sem a necessidade de adicionar ao carrinho antes.
- **Rastreio**: pedidos com código de rastreio da Frenet exibem o status de entrega consultado via `getTracking()`.

## Suporte

- Dúvidas sobre a API/token da Frenet: [contato@frenet.com.br](mailto:contato@frenet.com.br) ou o [painel da Frenet](http://painel.frenet.com.br/).
- Bugs e sugestões neste módulo: abra uma [issue no GitHub](https://github.com/FrenetGatewaydeFretes/frenet-magento2/issues).

## Desenvolvimento

O módulo segue o padrão de código `Magento2` (PHPCS) e PHPMD. Para rodar as verificações localmente:

```bash
composer coding-standard   # phpcs + phpmd
composer phpunit           # testes unitários
```

## Licença

Distribuído sob a licença [MIT](LICENSE.md).
