<?php

/**
 * @file
 * Post update functions for xom_web_services.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Restructure the News and Changelog block into per-release h3/p/ul markup.
 *
 * One-time companion to the FeedHelper template change: new releases are
 * prepended in the h3/p/ul format, and this converts the entries that are
 * already in the block. Also drops the duplicated May 22, 2026 entry and
 * fixes the "fulscreen" typo in the 5.4.1 entry.
 */
function xom_web_services_post_update_restructure_changelog() {
  $block = BlockContent::load(15);
  $paragraph = $block ? Paragraph::load($block->get('field_c_b_components')->getValue()[0]['target_id']) : NULL;
  if (!$paragraph) {
    return t('News and Changelog block/paragraph not found; nothing to do.');
  }

  $current = $paragraph->get('field_c_p_content')->getValue()[0]['value'];
  if (str_contains($current, '<h3>')) {
    return t('Changelog is already in the h3/p/ul format; skipped.');
  }
  if (!str_starts_with(trim($current), '<strong>May 30, 2026:')) {
    // A release newer than 5.4.2 landed in the old format after this hook
    // was written - don't clobber it. Restructure the block manually.
    \Drupal::logger('xom_web_services')->warning('Changelog restructure skipped: content does not start with the expected 5.4.2 entry.');
    return t('Changelog restructure SKIPPED - unexpected newest entry, convert manually.');
  }

  $body = <<<'EOT'
  <h3>May 30, 2026:</h3>
  <p>XIV on Mac 5.4.2 Beta</p>
  <ul>
  	<li>Fixed an issue, where borderless fullscreen was not working as expected on non-primary monitors.</li>
  </ul>

  <h3>May 23, 2026:</h3>
  <p>XIV on Mac 5.4.1 Beta</p>
  <ul>
  	<li>Now properly supports the in-game fullscreen display mode.</li>
  	<li>Fixed an issue, where some controllers were not working as expected.</li>
  </ul>

  <h3>May 23, 2026:</h3>
  <p>XIV on Mac 5.4 Beta</p>
  <ul>
  	<li>Updated Wine to 11.0.</li>
  	<li>Updated DXMT to cc5be43.</li>
  	<li>Various IME fixes.</li>
  </ul>

  <h3>April 30, 2026:</h3>
  <p>XIV on Mac 5.3.3 Beta</p>
  <ul>
  	<li>Updated DXMT to b2b35b1.</li>
  	<li>Minor fixes.</li>
  </ul>

  <h3>April 28, 2026:</h3>
  <p>XIV on Mac 5.3.2 Beta</p>
  <ul>
  	<li>Minor fixes.</li>
  </ul>

  <h3>December 16, 2025:</h3>
  <p>XIV on Mac 5.3.1 Beta</p>
  <ul>
  	<li>Fixed an issue that caused Steam Licenses to not properly work.</li>
  </ul>

  <h3>December 15, 2025:</h3>
  <p>XIV on Mac 5.3 Beta</p>
  <ul>
  	<li>Updated DXMT to version 0.72.</li>
  	<li>Added a Dalamud branch switcher.</li>
  	<li>Minor fixes.</li>
  </ul>

  <h3>September 28, 2025:</h3>
  <p>XIV on Mac 5.2.3 Beta</p>
  <ul>
  	<li>Worked around a macOS Tahoe bug, where the game would start having microstutters after a while.</li>
  </ul>

  <h3>September 16, 2025:</h3>
  <p>XIV on Mac 5.2.2 Beta</p>
  <ul>
  	<li>Updated design to better fit in with macOS Tahoe.</li>
  	<li>Minor fixes.</li>
  </ul>
  EOT;

  $paragraph->set('field_c_p_content', ['value' => $body, 'format' => 'basic_html']);
  $paragraph->setNewRevision(FALSE);
  $paragraph->save();
  return t('Changelog restructured into h3/p/ul markup.');
}
