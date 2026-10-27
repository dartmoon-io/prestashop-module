/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

import {initForms} from '../../vendor/dartmoon/prestashop-module-toolkit/resources/js/forms.js';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initForms());
} else {
  initForms();
}
