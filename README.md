# Frenet Gateway de Fretes para Magento 2
Integre seu Magento 2 aos serviços da Frenet de forma rápida e fácil.

[![Build Status](https://travis-ci.org/FrenetGatewaydeFretes/frenet-magento2.svg?branch=2.2-develop)](https://travis-ci.org/FrenetGatewaydeFretes/frenet-magento2)
![Packagist](https://img.shields.io/packagist/dt/frenet/frenet-magento2)
[![PHP v7.0](https://img.shields.io/badge/php-v7.0-blue.svg)](http://www.php.net)
[![Magento v2.3](https://img.shields.io/badge/magento-v2.3-green.svg)](https://magento.com/)

## Compatibilidade

Esta versão do módulo é compatível com as seguintes versões do Magento 2:

- Magento 2.3

## Instalação
> É recomendado que você tenha um ambiente de testes para validar alterações e atualizações antes de atualizar sua loja em produção.

> A instalação do módulo é feita utilizando o Composer. Para baixar e instalar o Composer no seu ambiente acesse https://getcomposer.org/download/ e caso tenha dúvidas de como utilizá-lo consulte a [documentação oficial do Composer](https://getcomposer.org/doc/).

Abra o terminal e navegue até o diretório raíz da sua instalação do Magento 2 e execute os seguintes comandos:

```
> composer require frenet/frenet-magento2        // Faz a requisição do módulo da Frenet
> php bin/magento module:enable Frenet_Shipping  // Ativa o módulo
> php bin/magento setup:upgrade                  // Registra a extensão
> php bin/magento setup:di:compile               // Recompila o projeto Magento
```
