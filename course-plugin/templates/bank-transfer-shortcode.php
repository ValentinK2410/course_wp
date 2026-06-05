<?php
/**
 * Шаблон [mbs_bank_transfer].
 *
 * @var array $context
 */

if (!defined('ABSPATH')) {
    exit;
}

$details = array(
    array(
        'key'   => 'recipient',
        'label' => __('Получатель', 'course-plugin'),
        'value' => $context['recipient'],
        'copy'  => false,
    ),
    array(
        'key'   => 'account',
        'label' => __('Расчётный счёт', 'course-plugin'),
        'value' => $context['account'],
        'copy'  => true,
    ),
    array(
        'key'   => 'bank',
        'label' => __('Банк', 'course-plugin'),
        'value' => $context['bank_name'],
        'copy'  => false,
    ),
    array(
        'key'   => 'corr',
        'label' => __('Корр. счёт', 'course-plugin'),
        'value' => $context['corr_account'],
        'copy'  => true,
    ),
    array(
        'key'   => 'bik',
        'label' => __('БИК', 'course-plugin'),
        'value' => $context['bik'],
        'copy'  => true,
    ),
    array(
        'key'   => 'inn',
        'label' => __('ИНН', 'course-plugin'),
        'value' => $context['inn'],
        'copy'  => true,
    ),
);
?>

<div class="mbt" data-mbt-version="<?php echo esc_attr(COURSE_PLUGIN_VERSION); ?>">
    <?php if (!empty($context['payment_notice'])) : ?>
        <div class="mbt-alert<?php echo (!empty($context['payment_status']) && $context['payment_status'] === 'fail') ? ' mbt-alert--fail' : ' mbt-alert--success'; ?>" role="status">
            <?php echo esc_html($context['payment_notice']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($context['is_configured'])) : ?>
        <div class="mbt-alert mbt-alert--warn" role="alert">
            <?php esc_html_e('Онлайн-оплата временно недоступна: не настроен платёжный шлюз Сбербанка. Обратитесь к администратору сайта.', 'course-plugin'); ?>
        </div>
    <?php endif; ?>

    <div class="mbt__grid">
        <section class="mbt-card mbt-card--details" aria-labelledby="mbt-details-title">
            <div class="mbt-card__hero">
                <div class="mbt-card__hero-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="mbt-card__kicker"><?php esc_html_e('Банковский перевод', 'course-plugin'); ?></p>
                    <h3 class="mbt-card__title" id="mbt-details-title"><?php esc_html_e('Реквизиты для перевода', 'course-plugin'); ?></h3>
                </div>
            </div>

            <ul class="mbt-details">
                <?php foreach ($details as $row) : ?>
                    <li class="mbt-details__row">
                        <span class="mbt-details__label"><?php echo esc_html($row['label']); ?></span>
                        <div class="mbt-details__value-wrap">
                            <span class="mbt-details__value" data-mbt-value="<?php echo esc_attr($row['key']); ?>">
                                <?php echo esc_html($row['value']); ?>
                            </span>
                            <?php if ($row['copy']) : ?>
                                <button
                                    type="button"
                                    class="mbt-copy"
                                    data-mbt-copy="<?php echo esc_attr($row['value']); ?>"
                                    title="<?php echo esc_attr(sprintf(__('Скопировать %s', 'course-plugin'), $row['label'])); ?>"
                                >
                                    <span class="mbt-copy__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="9" y="9" width="11" height="11" rx="2" stroke="#21A038" stroke-width="1.6"/>
                                            <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" stroke="#21A038" stroke-width="1.6"/>
                                        </svg>
                                    </span>
                                    <span class="mbt-copy__text"><?php esc_html_e('Копировать', 'course-plugin'); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <button type="button" class="mbt-btn mbt-btn--ghost mbt-btn--full" data-mbt-copy-all>
                <?php esc_html_e('Скопировать все реквизиты', 'course-plugin'); ?>
            </button>
        </section>

        <section class="mbt-card mbt-card--pay" aria-labelledby="mbt-pay-title">
            <div class="mbt-card__hero mbt-card__hero--pay">
                <div class="mbt-card__hero-icon mbt-card__hero-icon--pay" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="5" width="20" height="14" rx="3" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M2 10h20" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M6 15h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <p class="mbt-card__kicker"><?php esc_html_e('ПАО Сбербанк', 'course-plugin'); ?></p>
                    <h3 class="mbt-card__title" id="mbt-pay-title"><?php esc_html_e('Оплатить картой через Сбербанк', 'course-plugin'); ?></h3>
                </div>
            </div>

            <?php if (!empty($context['gateway_test'])) : ?>
                <p class="mbt-test-badge"><?php esc_html_e('Тестовая среда платёжного шлюза', 'course-plugin'); ?></p>
            <?php endif; ?>

            <form
                class="mbt-form"
                method="post"
                action="#"
                data-mbt-form
                data-mbt-sberbank="1"
                novalidate
            >
                <div class="mbt-field">
                    <label class="mbt-field__label" for="mbt-sum"><?php esc_html_e('Сумма, ₽', 'course-plugin'); ?></label>
                    <div class="mbt-sum">
                        <input
                            class="mbt-input mbt-input--sum"
                            type="text"
                            inputmode="numeric"
                            name="sum"
                            id="mbt-sum"
                            value="<?php echo esc_attr((string) $context['default_sum']); ?>"
                            autocomplete="off"
                            required
                        />
                    </div>
                    <div class="mbt-presets" role="group" aria-label="<?php esc_attr_e('Быстрый выбор суммы', 'course-plugin'); ?>">
                        <?php foreach ($context['preset_sums'] as $preset) : ?>
                            <button
                                type="button"
                                class="mbt-preset<?php echo ((int) $preset === (int) $context['default_sum']) ? ' is-active' : ''; ?>"
                                data-mbt-preset="<?php echo esc_attr((string) $preset); ?>"
                            >
                                <?php echo esc_html(number_format_i18n($preset)); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mbt-field">
                    <label class="mbt-field__label" for="mbt-clientid"><?php esc_html_e('ФИО', 'course-plugin'); ?></label>
                    <input
                        class="mbt-input"
                        type="text"
                        name="clientid"
                        id="mbt-clientid"
                        placeholder="<?php esc_attr_e('Фамилия Имя Отчество', 'course-plugin'); ?>"
                        autocomplete="name"
                        required
                    />
                </div>

                <div class="mbt-field">
                    <label class="mbt-field__label" for="mbt-email"><?php esc_html_e('Email', 'course-plugin'); ?></label>
                    <input
                        class="mbt-input"
                        type="email"
                        name="client_email"
                        id="mbt-email"
                        placeholder="<?php esc_attr_e('myname@domain.ru', 'course-plugin'); ?>"
                        autocomplete="email"
                        required
                    />
                </div>

                <div class="mbt-field">
                    <label class="mbt-field__label" for="mbt-comment"><?php esc_html_e('Комментарий к пожертвованию', 'course-plugin'); ?></label>
                    <input
                        class="mbt-input"
                        type="text"
                        name="service_name"
                        id="mbt-comment"
                        placeholder="<?php esc_attr_e('Например: пожертвование на общие нужды', 'course-plugin'); ?>"
                    />
                </div>

                <button type="submit" class="mbt-btn mbt-btn--primary mbt-btn--full"<?php echo empty($context['is_configured']) ? ' disabled' : ''; ?>>
                    <?php esc_html_e('Перейти к оплате в Сбербанке', 'course-plugin'); ?>
                </button>
            </form>
        </section>
    </div>

    <p class="mbt-note">
        <?php esc_html_e('При оплате обучения сообщите бухгалтерии о факте оплаты. Пожертвования поступают на счёт семинарии; возврат пожертвований не производится (ГК РФ ч. 2, ст. 582).', 'course-plugin'); ?>
    </p>

    <div class="mbt-toast" role="status" aria-live="polite" hidden></div>
</div>
