<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage } from '@/api/client'
import BrandingAssetField from '@/components/admin/BrandingAssetField.vue'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useFlashSaved } from '@/composables/useFlashSaved'
import { useTenantSettings } from '@/composables/useTenantSettings'
import { useUiStore } from '@/stores/ui'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'

const { t } = useI18n()
const ui = useUiStore()
const { current, load, save, saving } = useTenantSettings()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  name: '',
  tagline: '',
  primary_color: '#0B6E6B',
  accent_color: '#E07A5F',
  logo_url: null as string | null,
  favicon_url: null as string | null,
  menu_header_url: null as string | null,
})

const uploading = ref<'logo' | 'favicon' | 'menu_header' | null>(null)

async function hydrate() {
  const data = await load()
  form.value = {
    name: data.name,
    tagline: data.branding.tagline,
    primary_color: data.branding.primary_color,
    accent_color: data.branding.accent_color,
    logo_url: data.branding.logo_url,
    favicon_url: data.branding.favicon_url,
    menu_header_url: data.branding.menu_header_url,
  }
}

async function onSave() {
  await save({
    name: form.value.name,
    branding: {
      tagline: form.value.tagline,
      primary_color: form.value.primary_color,
      accent_color: form.value.accent_color,
    },
  })
  flashSaved()
  await hydrate()
}

async function uploadAsset(
  asset: 'logo' | 'favicon' | 'menu_header',
  file: File,
  endpoint: string,
) {
  uploading.value = asset
  const body = new FormData()
  body.append(asset === 'menu_header' ? 'menu_header' : asset, file)
  try {
    await api.post(endpoint, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    ui.success(t('admin.saved'))
    await hydrate()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    uploading.value = null
  }
}

async function removeAsset(
  pathKey: 'logo_path' | 'favicon_path' | 'menu_header_path',
  previewKey: 'logo_url' | 'favicon_url' | 'menu_header_url',
) {
  const urlKey = pathKey.replace('_path', '_url')
  await save({
    branding: {
      [pathKey]: null,
      [urlKey]: null,
    },
  })
  form.value[previewKey] = null
  flashSaved()
  await hydrate()
}

onMounted(hydrate)
</script>

<template>
  <SettingsPanel :saved="saved">
    <template #saved>{{ t('admin.saved') }}</template>
    <template #summary>
      <div class="d-flex align-center ga-3 mb-2">
        <v-avatar v-if="current.branding.logo_url" size="40" rounded="sm">
          <v-img :src="resolvePublicAssetUrl(current.branding.logo_url) || undefined" cover />
        </v-avatar>
        <div>
          <div class="text-h6">{{ current.name || '—' }}</div>
          <div class="text-body-2 text-medium-emphasis">{{ current.branding.tagline || '—' }}</div>
        </div>
      </div>
      <div class="d-flex flex-wrap ga-4">
        <div>
          <div class="text-caption text-medium-emphasis">{{ t('admin.primaryColor') }}</div>
          <div class="d-flex align-center ga-2">
            <span class="swatch" :style="{ background: current.branding.primary_color }" />
            {{ current.branding.primary_color }}
          </div>
        </div>
        <div>
          <div class="text-caption text-medium-emphasis">{{ t('admin.secondaryColor') }}</div>
          <div class="d-flex align-center ga-2">
            <span class="swatch" :style="{ background: current.branding.accent_color }" />
            {{ current.branding.accent_color }}
          </div>
        </div>
      </div>
    </template>

    <v-row dense>
      <v-col cols="12" md="6">
        <v-text-field v-model="form.name" :label="t('admin.venueName')" />
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field v-model="form.tagline" :label="t('admin.tagline')" :hint="t('admin.taglineHint')" persistent-hint />
      </v-col>

      <v-col cols="12">
        <p class="text-subtitle-2 mb-2">{{ t('admin.brandingAssets') }}</p>
      </v-col>
      <v-col cols="12" sm="4">
        <BrandingAssetField
          input-id="branding-logo"
          :label="t('admin.logo')"
          :hint="t('admin.logoHint')"
          :preview-url="form.logo_url"
          :uploading="uploading === 'logo'"
          @upload="uploadAsset('logo', $event, '/admin/branding/logo')"
          @remove="removeAsset('logo_path', 'logo_url')"
        />
      </v-col>
      <v-col cols="12" sm="4">
        <BrandingAssetField
          input-id="branding-favicon"
          :label="t('admin.favicon')"
          :hint="t('admin.faviconHint')"
          :preview-url="form.favicon_url"
          :uploading="uploading === 'favicon'"
          @upload="uploadAsset('favicon', $event, '/admin/branding/favicon')"
          @remove="removeAsset('favicon_path', 'favicon_url')"
        />
      </v-col>
      <v-col cols="12">
        <BrandingAssetField
          input-id="branding-menu-header"
          aspect="wide"
          :label="t('admin.menuHeader')"
          :hint="t('admin.menuHeaderHint')"
          :preview-url="form.menu_header_url"
          :uploading="uploading === 'menu_header'"
          @upload="uploadAsset('menu_header', $event, '/admin/branding/menu-header')"
          @remove="removeAsset('menu_header_path', 'menu_header_url')"
        />
      </v-col>

      <v-col cols="6" md="3">
        <v-text-field v-model="form.primary_color" :label="t('admin.primaryColor')" type="color" />
      </v-col>
      <v-col cols="6" md="3">
        <v-text-field v-model="form.accent_color" :label="t('admin.secondaryColor')" :hint="t('admin.secondaryColorHint')" persistent-hint type="color" />
      </v-col>
      <v-col cols="12">
        <p class="text-caption text-medium-emphasis mb-0">{{ t('admin.colorHint') }}</p>
      </v-col>
    </v-row>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>

<style scoped>
.swatch {
  width: 18px;
  height: 18px;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  display: inline-block;
}
</style>
