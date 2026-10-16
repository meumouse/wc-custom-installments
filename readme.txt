=== Parcelas Customizadas para WooCommerce ===
Contributors: meumouse
Tags: woocommerce, parcelamento, desconto, pix, formas de pagamento
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 6.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exiba parcelamentos, descontos para pagamento à vista e as formas de pagamento aceitas nos produtos da sua loja WooCommerce.

== Description ==

O Parcelas Customizadas para WooCommerce responde à pergunta que todo cliente faz antes de comprar: *quanto fica por mês, e quanto eu economizo pagando à vista?*

Ele exibe o parcelamento abaixo do preço do produto, na listagem da loja e na página do produto, usando a taxa de juros que a sua adquirente realmente cobra. Mostra o preço com desconto para pagamento à vista, leva esse desconto até o carrinho e a finalização da compra, e exibe as bandeiras de cartão e as formas de pagamento que você aceita.

= Parcelamento =

* Máximo de parcelas, parcelas sem juros e valor mínimo da parcela
* Uma taxa de juros mensal aplicada além das parcelas sem juros
* A melhor parcela exibida abaixo do preço, com ou sem juros, ou as duas
* A tabela completa de parcelas em um popup, em uma sanfona ou na página
* Preço com desconto exibido nos totais do carrinho

= Descontos =

* Desconto no preço exibido para pagamento à vista, em porcentagem ou valor fixo
* O desconto aplicado aos totais do carrinho e da finalização da compra, com ou sem o frete
* Opção por produto para não aplicar o desconto

= Formas de pagamento =

* Blocos de Pix, boleto bancário, cartão de crédito e cartão de débito
* Selos das principais bandeiras de cartão e intermediadores de pagamento

= No restante do site =

* Quinze shortcodes, um por elemento, que funcionam em qualquer construtor de páginas
* Widgets do Elementor para o grupo de preços e a tabela de parcelas
* Controles de estilo nos widgets de produto do Elementor Pro, do Woodmart e do XStore
* Compatibilidade com Astra, Woodmart, XStore, Shoptimizer, Machic e Ricky
* Funciona junto com Tiered Pricing Table, Dynamic Pricing & Discounts, Role Based Pricing for Woo e Rank Math
* Respeita lojas que escondem os preços: com o Role Based Pricing for Woo escondendo o preço de quem não está logado, nenhum elemento do plugin devolve esse preço
* Uma aba de Integrações nas configurações, com um cartão por plugin integrado, dizendo se ele foi encontrado e o que você decide sobre ele
* Um assistente de configuração que deixa uma loja nova exibindo parcelas em cerca de um minuto
* Ajustes por produto, no painel do produto e na edição rápida

= Idiomas =

O plugin é escrito em português do Brasil e acompanha a tradução para o inglês. Os demais idiomas chegam pelo translate.wordpress.org.

= Créditos =

Os ícones da loja usam o Font Awesome Free 6.7.2, da Fonticons, Inc. (https://fontawesome.com), incluído no plugin e carregado do seu próprio site, sem nenhuma requisição externa. Ícones sob CC BY 4.0, fontes sob SIL OFL 1.1 e código sob MIT — https://fontawesome.com/license/free

== External services ==

Este plugin não faz nenhuma requisição externa por conta própria.

O assistente de configuração oferece um teste grátis do complemento Pro. Se — e somente se — você preencher esse formulário e enviá-lo, seu nome, e-mail e telefone são enviados ao serviço de licenças da MeuMouse em https://cloud.meumouse.com para emitir a chave de teste, que depois é enviada para o seu e-mail. Nada é enviado antes de você enviar o formulário, e pular a etapa não envia nada.
Termos: https://meumouse.com/termos-condicionais/ — Privacidade: https://meumouse.com/politica-de-privacidade/

== Installation ==

1. Envie o plugin para `/wp-content/plugins/` ou instale-o em Plugins > Adicionar novo.
2. Ative o plugin.
3. O assistente de configuração abre automaticamente. Depois, as configurações ficam no menu Parcelas Customizadas > Configurações.

== Frequently Asked Questions ==

= Isso muda o preço que o cliente paga? =

A exibição das parcelas, não. O desconto à vista, sim: ele é aplicado aos totais do carrinho e da finalização da compra, e é justamente essa a ideia.

= Funciona com produtos variáveis? =

Sim. O parcelamento é calculado a partir do preço da variação selecionada.

= O plugin está disponível em inglês? =

Sim. Em um site configurado em inglês, o plugin aparece como "Custom Installments for WooCommerce", com todos os textos em inglês.

== Changelog ==

= 6.0.0 =

* Reconstruído do zero como plugin gratuito. Continua a numeração do Parcelas Customizadas para WooCommerce 5.x.
* Tela de configurações redesenhada, com o logo do plugin no cabeçalho.
* Textos em português do Brasil, com a tradução para o inglês incluída.
* Todas as opções da versão 5.5.9 de volta às configurações, incluindo a sanfona, as duas melhores parcelas, o shortcode e o widget das formas de pagamento, o estilo do botão de parcelas e o painel de design dos elementos.
* Estilos próprios para o preço com desconto, pré-visualização dos ícones no painel de design e as unidades REM, EM, Pixel e % em todas as medidas.

== Upgrade Notice ==

= 6.0.0 =
Substitui o plugin anterior "Parcelas Customizadas para WooCommerce". Suas configurações são importadas
automaticamente e nada do plugin antigo é apagado. Desative o plugin antigo depois de conferir a
sua loja.
