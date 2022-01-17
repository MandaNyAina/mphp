import {app} from '../js/app.module.js';

app.controller('myCtrl', ($scope, $http) => {
    $scope.uploadFilename = 'Upload';
    $scope.uploadFile = null;
    $scope.existFile = false;
    $scope.addFile = (e) => {
        $scope.uploadFile = e;
        $scope.existFile = true;
        $scope.uploadFilename = e.name;
    }
    $scope.removeFile = () => {
        $scope.uploadFile = null;
        $scope.existFile = false;
        $scope.uploadFilename = 'Upload';
    }

    $scope.avatarUploadSrc = null;
    $scope.avatarUpload = null;
    $scope.avatarExistFile = false;
    $scope.editAvatar = (e) => {
        if (e != undefined) {
            $scope.avatarExistFile = true;
            let data = new FileReader();
            data.readAsDataURL(e);
            data.onloadend = (result) => {
                document.getElementById('imageAvatarFile').src = result.target.result;
            }
        }
    }
    $scope.deleteAvatar = () => {
        $scope.avatarUploadSrc = null;
        $scope.avatarUpload = null;
        $scope.avatarExistFile = false;
    }
})