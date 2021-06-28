<template>
  <q-scroll-area class="full-height full-width">
    <div class="q-pa-lg">
      <div class="row q-mb-md">
        <div class="col text-h5">{{ $t('ACTIVESERVER.HEADING_SETTINGS_TAB') }}</div>
      </div>
      <q-card flat bordered class="card-edit-settings">
        <q-card-section>
          <div class="row q-my-sm">
            <q-checkbox dense v-model="enableActiveSync" color="teal">
              <q-item-label v-t="'ACTIVESERVER.LABEL_ENABLE_ACTIVESYNC'"/>
            </q-checkbox>
          </div>
        </q-card-section>
      </q-card>
      <div class="q-pt-md text-right">
        <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
               :label="saving ? $t('COREWEBCLIENT.ACTION_SAVE_IN_PROGRESS') : $t('COREWEBCLIENT.ACTION_SAVE')" @click="updateSettingsForEntity"/>
      </div>
    </div>
    <UnsavedChangesDialog ref="unsavedChangesDialog"/>
  </q-scroll-area>
</template>

<script>
import _ from 'lodash'

import errors from 'src/utils/errors'
import notification from 'src/utils/notification'
import typesUtils from 'src/utils/types'
import webApi from 'src/utils/web-api'

import cache from 'src/cache'
import core from 'src/core'

import UnsavedChangesDialog from 'src/components/UnsavedChangesDialog'

export default {
  name: 'ActiveSyncAdminSettingsPerUser',
  components: {
    UnsavedChangesDialog
  },
  data () {
    return {
      saving: false,
      enableActiveSync: false
    }
  },
  watch: {
    $route(to, from) {
      this.parseRoute()
    },
  },
  mounted() {
    this.parseRoute()
  },
  beforeRouteLeave (to, from, next) {
    if (this.hasChanges() && _.isFunction(this?.$refs?.unsavedChangesDialog?.openConfirmDiscardChangesDialog)) {
      this.$refs.unsavedChangesDialog.openConfirmDiscardChangesDialog(next)
    } else {
      next()
    }
  },
  methods: {
    hasChanges () {
      const enableActiveSync = _.isFunction(this.user?.getData) ? this.user?.getData('ActiveServer::Enabled') : false
      return this.enableActiveSync !== enableActiveSync
    },
    parseRoute () {
      const userId = typesUtils.pPositiveInt(this.$route?.params?.id)
      if (this.user?.id !== userId) {
        this.user = {
          id: userId,
        }
        this.populate()
      }
    },
    populate () {
      this.loading = true
      const currentTenantId = core.getCurrentTenantId()
      cache.getUser(currentTenantId, this.user.id).then(({ user, userId }) => {
        if (userId === this.user.id) {
          this.loading = false
          if (user && _.isFunction(user?.getData)) {
            this.user = user
            this.enableActiveSync = user.getData('ActiveServer::Enabled')
          } else {
            this.$emit('no-user-found')
          }
        }
      })
    },
    updateSettingsForEntity () {
      if (!this.saving) {
        this.saving = true
        const parameters = {
          UserId: this.user?.id,
          TenantId: this.user.tenantId,
          EnableModule: typesUtils.pBool(this.enableActiveSync),
        }
        webApi.sendRequest({
          moduleName: 'ActiveServer',
          methodName: 'UpdatePerUserSettings',
          parameters
        }).then(result => {
          this.saving = false
          if (result) {
            cache.getUser(parameters.TenantId, this.user?.id).then(({ user }) => {
              user.updateData([{
                field: 'ActiveServer::Enabled',
                value: typesUtils.pBool(this.enableActiveSync)
              }])
              this.populate()
            })
            notification.showReport(this.$t('COREWEBCLIENT.REPORT_SETTINGS_UPDATE_SUCCESS'))
          } else {
            notification.showError(this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED'))
          }
        }, response => {
          this.saving = false
          notification.showError(errors.getTextFromResponse(response, this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED')))
        })
      }
    },
  }
}
</script>