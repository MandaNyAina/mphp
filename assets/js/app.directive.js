import {app} from '../js/app.module.js';

app.directive("filesInput", function() {
    return {
      require: "ngModel",
      link: function postLink(scope,elem,attrs,ngModel) {
        elem.on("change", function(e) {
          var files = elem[0].files[0];
          ngModel.$setViewValue(files);
        })
      }
    }
})

app.directive('upload', () => {
    return {
        restrict: 'E',
        scope: {
            accept: '@type',
            name: '@name'
        },
        template: `
        <div ng-controller="myCtrl">
            <label for="upload" class="btn btn-outline-primary uploadBtn" style="padding-left: 20px;padding-right: 25px;"><i style="padding-right: 5px" class="fas fa-cloud-upload-alt"></i> {{uploadFilename}}</label>
            <input type="file" id="upload" accept="{{accept}}" files-input ng-model="uploadFile" ng-change="addFile(uploadFile)" name="{{name}}">
            <label ng-click="removeFile()" ng-if="existFile" class="btn btn-outline-danger" style="padding:0.6em 0.7em"><i class="fa fa-trash" aria-hidden="true"></i></label>
        </div>
        `
    }
});

app.directive('uploadAvatar', () => {
    return {
        restrict: 'E',
        scope: {
            src: '@src',
            name: '@name'
        },
        template: `
        <div class="blocavatarfile" ng-controller="myCtrl">
            <div class="blocavatar">
                <img ng-if="avatarExistFile" id="imageAvatarFile" class="avatar" src="{{avatarUploadSrc}}" alt="upload" />
                <img ng-if="!avatarExistFile" style="margin-top: -5px;" class="avatar" src="assets/images/upload.png" alt="upload" />
                <div style="padding:auto;padding-top:75px;position:relative;color:white" class="d-block-inline text-center avatarHover">
                    <label for="uploadAvatar"><i class="fas fa-pen selectSetter" data-toggle="tooltip" title="Editer"></i></label>
                    <input type="file" accept=".png,.jpg,.jpeg" id="uploadAvatar" files-input ng-model="avatarUpload" ng-change="editAvatar(avatarUpload)" name="{{name}}">
                    <span ng-click="deleteAvatar()"><i class="fa fa-trash selectSetter"  data-toggle="tooltip" title="Delete"></i></span>
                </div>
            </div>
        </div>
        `
    }
})