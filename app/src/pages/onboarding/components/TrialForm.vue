<script setup>
/**
 * TrialForm.vue - ask the licensing service for a seven-day key.
 *
 * The exact request body is shown before it is sent. A consent notice that
 * describes a request in prose is a promise; showing the payload is the thing
 * itself, and it costs one disclosure element.
 *
 * @since 6.0.0
 */
import { computed, reactive, ref } from 'vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseCode from '../../../components/base/BaseCode.vue';
import BaseForm from '../../../components/base/BaseForm.vue';
import BaseInput from '../../../components/base/BaseInput.vue';
import BaseLabel from '../../../components/base/BaseLabel.vue';
import BaseNotice from '../../../components/base/BaseNotice.vue';
import { __, sprintf } from '../../../utils/i18n';

const props = defineProps({
  api: { type: Object, required: true },
  pro: { type: Object, default: () => ({}) },
  links: { type: Object, default: () => ({}) },
});

const form = reactive({ first_name: '', last_name: '', email: '', phone: '' });
const isSending = ref(false);
const error = ref(null);
const trial = ref(null);
const showPayload = ref(false);

const canSubmit = computed(() =>
  Object.values(form).every((value) => String(value).trim() !== '') && !isSending.value,
);

const payload = computed(() => JSON.stringify({ productId: '…', ...form }, null, 2));

// The four fields, described once: the form renders them from this list.
const FIELDS = [
  { key: 'first_name', label: () => __('Nome'), type: 'text', autocomplete: 'given-name' },
  { key: 'last_name', label: () => __('Sobrenome'), type: 'text', autocomplete: 'family-name' },
  { key: 'email', label: () => __('E-mail'), type: 'email', autocomplete: 'email' },
  { key: 'phone', label: () => __('Telefone'), type: 'tel', autocomplete: 'tel' },
];

/**
 * Ask for the trial.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function submit() {
  isSending.value = true;
  error.value = null;

  try {
    const response = await props.api.post('trial', { ...form });

    if (response.status === 'error') {
      error.value = response.message;

      return;
    }

    trial.value = response.trial;
  } catch (requestError) {
    error.value = requestError.message || __('Não funcionou. Tente novamente.');
  } finally {
    isSending.value = false;
  }
}
</script>

<template>
  <BaseNotice v-if="pro.active" tone="info">
    {{ __('O complemento Pro já está instalado neste site.') }}
  </BaseNotice>

  <BaseNotice v-else-if="trial" tone="success" :title="__('Seu teste está pronto.')">
    <p>{{ __('Uma cópia está a caminho do seu e-mail. Instale o complemento Pro e cole esta chave na tela de licença dele.') }}</p>

    <div class="mt-3">
      <BaseCode>{{ trial.license_key }}</BaseCode>
    </div>

    <template #actions>
      <BaseButton :href="pro.store_url" external icon="download">
        {{ __('Baixar o complemento Pro') }}
      </BaseButton>
    </template>
  </BaseNotice>

  <!-- With no product configured there is nothing to ask for, so the step
       offers a link and sends nothing. -->
  <BaseNotice v-else-if="!pro.trial_available" icon="crown">
    {{ __('O Pro adiciona descontos por forma de pagamento, descontos progressivos, selos extras e controle total do design.') }}

    <template #actions>
      <BaseButton :href="pro.store_url" external icon="crown">
        {{ __('Veja o que o Pro adiciona') }}
      </BaseButton>
    </template>
  </BaseNotice>

  <BaseForm v-else class="flex flex-col gap-5" @submit="submit">
    <p class="text-[14px] leading-6 text-slate-600">
      {{ sprintf(__('Teste todos os recursos Pro por %d dias. Sem cartão e sem renovação automática.'), pro.trial_days) }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
      <div v-for="input in FIELDS" :key="input.key" class="flex flex-col gap-2">
        <BaseLabel :for="`wcci-trial-${input.key}`" size="sm">{{ input.label() }}</BaseLabel>
        <BaseInput
          :id="`wcci-trial-${input.key}`"
          v-model="form[input.key]"
          :type="input.type"
          :autocomplete="input.autocomplete"
          required
        />
      </div>
    </div>

    <BaseNotice icon="info-circle">
      {{ sprintf(__('Ao clicar no botão, estes quatro campos são enviados para %s para emitir sua chave. Nada foi enviado ainda, e pular esta etapa não envia nada.'), pro.api_host || __('o serviço de licenças')) }}

      <div v-if="showPayload" class="mt-3">
        <BaseCode block>{{ payload }}</BaseCode>
      </div>

      <template #actions>
        <BaseButton variant="link" @click="showPayload = !showPayload">
          {{ showPayload ? __('Ocultar o que é enviado') : __('Mostrar exatamente o que é enviado') }}
        </BaseButton>
        <span class="text-slate-300" aria-hidden="true">·</span>
        <BaseButton variant="link" :href="links.terms_url" external>{{ __('Termos') }}</BaseButton>
        <span class="text-slate-300" aria-hidden="true">·</span>
        <BaseButton variant="link" :href="links.privacy_url" external>{{ __('Privacidade') }}</BaseButton>
      </template>
    </BaseNotice>

    <BaseNotice v-if="error" tone="danger" live>
      {{ error }}
    </BaseNotice>

    <div>
      <BaseButton type="submit" icon="crown" :loading="isSending" :disabled="!canSubmit">
        {{ isSending ? __('Solicitando…') : __('Começar o teste grátis') }}
      </BaseButton>
    </div>
  </BaseForm>
</template>
