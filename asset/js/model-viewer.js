"use strict";

// Simplified from https://threejs.org/docs/#examples/en/loaders/GLTFLoader and https://threejsfundamentals.org/threejs/threejs-load-gltf.html.

document.addEventListener('DOMContentLoaded', function(event) {

    // The config is defined inside the html.
    if (typeof modelViewerOptions === 'undefined') {
        return;
    }

    function prepareThreeJs( options ) {
        // TODO Use page background color (or "white" or "lightgray" or #181818 or anything else).
        const background = 'white';

        const container = document.getElementById( options.id );
        const canvas = document.querySelector( '#' + options.id + ' canvas' );

        if (!canvas) {
            console.log('No canvas.');
            return;
        }

        function main() {
            const renderer = new THREE.WebGLRenderer({
                canvas: canvas,
                // TODO Disable antialias on low mobile devices.
                antialias: true,
            });

            const scene = new THREE.Scene();

            const fov = 45;
            const aspect = container.clientWidth / container.clientHeight;
            const near = 0.01;
            const far = 10000;
            const camera = new THREE.PerspectiveCamera( fov, aspect, near, far );
            camera.position.set( 0, 10, 20 );

            const controls = new THREE.OrbitControls( camera, canvas );
            controls.target.set( 0, 5, 0 );
            controls.update();

            {
                const pmremGenerator = new THREE.PMREMGenerator( renderer );
                scene.background = new THREE.Color( 'white' );
                scene.environment = pmremGenerator.fromScene( scene ).texture;
                scene.background = new THREE.Color( background );
                renderer.outputEncoding = THREE.sRGBEncoding;
                renderer.physicallyCorrectLights = true;
                renderer.toneMapping = THREE.ACESFilmicToneMapping;
                renderer.gammaFactor = 2.2;
            }

            {
                const color = 0xFFFFFF;
                const intensity = 0.3;
                const light = new THREE.AmbientLight( color, intensity );
                scene.add( light );
            }

            {
                const color = 0xFFFFFF;
                const intensity = 1;
                const light = new THREE.DirectionalLight( color, intensity );
                light.position.set( 1, 1, 1 );
                scene.add( light );
            }

            function frameArea( sizeToFitOnScreen, boxSize, boxCenter, camera ) {
                const halfSizeToFitOnScreen = sizeToFitOnScreen * 0.5;
                const halfFovY = THREE.MathUtils.degToRad( camera.fov * .5 );
                const distance = halfSizeToFitOnScreen / Math.tan( halfFovY );
                const direction = ( new THREE.Vector3() )
                    .subVectors( camera.position, boxCenter )
                    .multiply( new THREE.Vector3(1, 0, 1) )
                    .normalize();
                camera.position.copy( direction.multiplyScalar( distance ).add(boxCenter) );
                camera.near = boxSize / 1000;
                camera.far = boxSize * 1000;
                camera.updateProjectionMatrix();
                camera.lookAt( boxCenter.x, boxCenter.y, boxCenter.z );
            }

            const onLoaderProgress = function ( xhr ) {
                var url = xhr.srcElement.responseURL;
                var size = Math.floor( xhr.total / 1000 );
                var progress = Math.floor( ( xhr.loaded / xhr.total ) * 100 );
                console.log( `Loading ${url.substring(url.lastIndexOf('/') + 1)} (${size} KB) ${progress}%` );
            }

            const onLoaderError = function ( error ) {
                console.log( error.toString() );
                container.innerHTML = error.toString();
                container.style.height = 'auto';
                container.classList.add('error');
            }

            if (!options.mediaType
                || options.mediaType === 'model/gltf-binary'
                || options.mediaType === 'model/gltf+json'
            ) {
                const gltfLoader = new THREE.GLTFLoader();
                gltfLoader
                    .setPath( options.dirpath )
                    .load(
                        options.filename,
                        function ( gltf ) {
                            scene.add( gltf.scene );

                            gltf.animations;
                            gltf.scene;
                            gltf.scenes;
                            gltf.cameras;
                            gltf.asset;

                            const box = new THREE.Box3().setFromObject( gltf.scene );
                            const boxSize = box.getSize( new THREE.Vector3() ).length();
                            const boxCenter = box.getCenter( new THREE.Vector3() );
                            frameArea(boxSize, boxSize, boxCenter, camera);

                            controls.maxDistance = boxSize * 100;
                            controls.target.copy( boxCenter );
                            controls.update();
                        },
                        onLoaderProgress,
                        onLoaderError
                    );
            } else if (options.mediaType === 'application/vnd.threejs+json') {
                const loader = new THREE.VRMLoader();
                loader
                    .load(
                        options.source,
                        function ( obj ) {
                            scene.add( obj );

                            const box = new THREE.Box3().setFromObject( obj );
                            const boxSize = box.getSize( new THREE.Vector3() ).length();
                            const boxCenter = box.getCenter( new THREE.Vector3() );
                            frameArea( boxSize, boxSize, boxCenter, camera );

                            controls.maxDistance = boxSize * 100;
                            controls.target.copy( boxCenter );
                            controls.update();
                        },
                        onLoaderProgress,
                        onLoaderError
                    );
            }

            function resizeRendererToDisplaySize( renderer ) {
                const canvas = renderer.domElement;
                const width = canvas.clientWidth;
                const height = canvas.clientHeight;
                const needResize = canvas.width !== width || canvas.height !== height;
                if ( needResize ) {
                    renderer.setSize( width, height, false );
                }
                return needResize;
            }

            function render() {
                if (resizeRendererToDisplaySize( renderer )) {
                    const canvas = renderer.domElement;
                    camera.aspect = container.clientWidth / container.clientHeight;
                    camera.updateProjectionMatrix();
                }

                renderer.render( scene, camera );

                requestAnimationFrame( render );
            }

            requestAnimationFrame( render );
        }

        main();
    }

    modelViewerOptions.forEach(function (options, index) {
        prepareThreeJs(options);
    });

});
