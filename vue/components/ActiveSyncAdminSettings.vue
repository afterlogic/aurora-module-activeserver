<template>
  <q-scroll-area class="full-height full-width">
    <div class="q-pa-lg">
      <div class="row q-mb-md">
        <div class="col text-h5">{{ $t('ACTIVESERVER.HEADING_SETTINGS_TAB') }}</div>
      </div>
      <q-card flat bordered class="card-edit-settings">
        <q-card-section>
          <div class="row q-mb-sm">
            <div class="col-2" v-t="'ACTIVESERVER.LABEL_LICENSING_MAX_NUMBER_OF_USERS'"></div>
            <div class="col-5 text-weight-medium">
              <span>{{ licensedUsersCount }}</span>
            </div>
          </div>
          <div class="row q-mb-md">
            <div class="col-2" v-t="'ACTIVESERVER.LABEL_LICENSING_USERS_ALLOCATED'"></div>
            <div class="col-5 text-weight-medium">
              <span>{{ usersCount }}</span>
            </div>
          </div>
          <div class="row q-mb-md">
            <div class="col-2" v-t="'ACTIVESERVER.LABEL_LICENSING_FREE_SLOTS'"></div>
            <div class="col-5 text-weight-medium">
              <span>{{ usersFreeSlots }}</span>
            </div>
          </div>
          <div class="row q-mb-md">
            <div class="col-2" v-t="'ACTIVESERVER.LABEL_LICENSING_TYPE'"></div>
            <div class="col-5 text-weight-medium">
              <span>{{ licenceType }}</span>
            </div>
          </div>
          <div class="row q-my-md">
            <div class="col-2 q-my-sm" v-t="'ACTIVESERVER.LABEL_SERVER_HOST'"></div>
            <div class="col-5">
              <q-input outlined dense bg-color="white" v-model="server"/>
            </div>
          </div>
          <div class="row">
            <div>
              <q-item-label caption v-html="$t('ACTIVESERVER.HINT_SERVER_HOST')"/>
            </div>
          </div>
          <div class="row q-my-md">
            <div class="col-2 q-my-sm" v-t="'ACTIVESERVER.LABEL_LINK'"></div>
            <div class="col-5">
              <q-input outlined dense bg-color="white" v-model="linkToManual"/>
            </div>
          </div>
          <div class="row">
            <div>
              <q-item-label caption v-html="$t('ACTIVESERVER.HINT_LINK_TO_MANUAL')"/>
            </div>
          </div>
          <div class="row q-my-md">
            <q-checkbox dense v-model="enableForNewUsers">
              <q-item-label>{{ $t('ACTIVESERVER.ENABLE_FOR_NEW_USERS') }}</q-item-label>
            </q-checkbox>
          </div>
          <div class="row">
            <div>
              <q-item-label caption v-html="$t('ACTIVESERVER.HINT_FOR_NEW_USERS')"/>
            </div>
          </div>
        </q-card-section>
      </q-card>
      <div class="q-pt-md text-right">
        <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary" @click="save"
               :label="$t('COREWEBCLIENT.ACTION_SAVE')">
        </q-btn>
      </div>
    </div>
    <q-inner-loading style="justify-content: flex-start;" :showing="saving">
      <q-linear-progress query />
    </q-inner-loading>
  </q-scroll-area>
</template>

<script>

import errors from 'src/utils/errors'
import notification from 'src/utils/notification'
import webApi from 'src/utils/web-api'

import settings from '../settings'

export default {
  name: 'ActiveSyncAdminSettings',

  data () {
    return {
      saving: false,
      enableModule: false,
      enableModuleForUser: false,
      enableForNewUsers: false,
      usersCount: 0,
      licensedUsersCount: 0,
      usersFreeSlots: 0,
      server: '',
      linkToManual: '',
      productName: '',
      licenceType: ''
    }
  },
  mounted () {
    this.populate()
    this.getLicenseInfo()
    this.getSettings()
  },

  beforeRouteLeave (to, from, next) {
    this.$root.doBeforeRouteLeave(to, from, next)
  },

  methods: {
    /**
     * Method is used in doBeforeRouteLeave mixin
     */
    hasChanges () {
      const data = settings.getActiveServerSettings()
      return this.enableForNewUsers !== data.enableForNewUsers ||
          this.server !== data.server ||
          this.linkToManual !== data.linkToManual
    },

    /**
     * Method is used in doBeforeRouteLeave mixin,
     * do not use async methods - just simple and plain reverting of values
     * !! hasChanges method must return true after executing revertChanges method
     */
    revertChanges () {
      this.populate()
    },

    save () {
      if (!this.saving) {
        this.saving = true
        const parameters = {
          EnableModule: true, // it must be sent, otherwise the module will be disabled at all
          EnableForNewUsers: this.enableForNewUsers,
          Server: this.server,
          LinkToManual: this.linkToManual
        }
        webApi.sendRequest({
          moduleName: 'ActiveServer',
          methodName: 'UpdateSettings',
          parameters,
        }).then(result => {
          this.saving = false
          if (result === true) {
            settings.saveActiveServerSettings({
              server: this.server,
              enableForNewUsers: this.enableForNewUsers,
              enableModule: this.enableModule,
              linkToManual: this.linkToManual
            })
            this.populate()
            this.getSettings()
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
    getLicenseInfo () {
      webApi.sendRequest({
        moduleName: 'ActiveServer',
        methodName: 'GetLicenseInfo',
      }).then(result => {
        this.licenceType = this.$t('LICENSINGWEBCLIENT.LABEL_TYPE_INVALID')
        if (result) {
          switch (result.Type) {
            case 0:
              this.licenceType = this.$t('LICENSINGWEBCLIENT.LABEL_TYPE_UNLIM')
              break
            case 1:
              this.licenceType = this.$tc('LICENSINGWEBCLIENT.LABEL_TYPE_PERMANENT_PLURAL', result.Count, { COUNT: result.Count })
              break
            case 2:
              this.licenceType = this.$tc('LICENSINGWEBCLIENT.LABEL_TYPE_DOMAINS_PLURAL', result.Count, { COUNT: result.Count })
              break
            case 4:
              if (result.ExpiresIn < 1) {
                this.licenceType = this.$t('LICENSINGWEBCLIENT.LABEL_TYPE_OUTDATED_INFO')
              }
              break
            case 3:
            case 10:
              this.licenceType = result.Type === 3
                ? this.$tc('LICENSINGWEBCLIENT.LABEL_TYPE_ANNUAL_PLURAL', result.Count, { COUNT: result.Count })
                : this.$t('LICENSINGWEBCLIENT.LABEL_TYPE_TRIAL')
              if (result.ExpiresIn !== '*') {
                if (result.ExpiresIn > 0) {
                  this.licenceType += this.$tc('LICENSINGWEBCLIENT.LABEL_TYPE_EXPIRES_IN_PLURAL', result.ExpiresIn, { DAYS: result.ExpiresIn })
                } else {
                  this.licenceType += this.$t('LICENSINGWEBCLIENT.LABEL_TYPE_EXPIRED') + ' ' + this.$t('LICENSINGWEBCLIENT.LABEL_TYPE_OUTDATED_INFO')
                }
              }
              break
          }
        }
      })
    },
    getSettings () {
      webApi.sendRequest({
        moduleName: 'ActiveServer',
        methodName: 'GetSettings',
      }).then(result => {
        this.usersCount = result.UsersCount
        this.licensedUsersCount = result.LicensedUsersCount
        this.usersFreeSlots = result.UsersFreeSlots
      })
    },
    populate () {
      const data = settings.getActiveServerSettings()
      this.enableModule = data.enableModule
      this.enableModuleForUser = data.enableModuleForUser
      this.enableForNewUsers = data.enableForNewUsers
      this.server = data.server
      this.linkToManual = data.linkToManual
      this.productName = data.productName
    }
  }
}
</script>

<style scoped>

</style>
