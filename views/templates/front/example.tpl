{* Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
SPDX-License-Identifier: MIT
See LICENSE for the full license text. *}
{extends file='page.tpl'}

{block name='page_title'}
  {$example_module_name|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
  <section id="module-front-example">
    <p>{l s='This is your module front-office example page.' mod='examplemodule'}</p>
  </section>
{/block}
