/**
 * Used Modules Inside Of Build Process
 */
const gulp      = require('gulp'),
      sass      = require('gulp-sass'),
      babel     = require('gulp-babel'),
      concat    = require('gulp-concat'),
      uglify    = require('gulp-uglify'),
      uglifycss = require('gulp-uglifycss'),
      rename    = require('gulp-rename');

/**
 * Configures Environment For Build Process
 */
const bridgeScssPath        = 'src/Cabin/Bridge/public/src/scss',
      bridgeCompiledCSSPath = 'src/Cabin/Bridge/public/css',
      bridgeJSPath          = 'src/Cabin/Bridge/public/src/js',
      bridgeCompiledJSPath  = 'src/Cabin/Bridge/public/js',
      hullScssPath         = 'src/Cabin/Hull/public/src/scss',
      hullCompiledCSSPath  = 'src/Cabin/Hull/public/css',
      hullJSPath           = 'src/Cabin/Hull/public/src/js',
      hullCompiledJSPath   = 'src/Cabin/Hull/public/js';

/**
 * Runs all of the build steps for the bridge
 */
gulp.task('bridge-styles', () => {
    return gulp
        .src(bridgeScssPath + '/bridge.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest(bridgeCompiledCSSPath))
});

gulp.task('bridge-scripts', () => {
    return gulp
        .src(bridgeJSPath + '/**/*.js')
        .pipe(concat('bridge.js'))
        .pipe(babel({
            presets: ['es2015']
        }))
        .pipe(gulp.dest(bridgeCompiledJSPath))
});

gulp.task('uglify-bridge-styles', () => {
    return gulp
        .src(bridgeCompiledCSSPath + '/bridge.css')
        .pipe(uglifycss())
        .pipe(rename('bridge.min.css'))
        .pipe(gulp.dest(bridgeCompiledCSSPath))
});

gulp.task('uglify-bridge-scripts', () => {
    return gulp
        .src(bridgeCompiledJSPath + '/bridge.js')
        .pipe(uglify())
        .pipe(rename('bridge.min.js'))
        .pipe(gulp.dest(bridgeCompiledJSPath))
});

/**
 * Runs all of the build steps for the hull
 */
gulp.task('hull-styles', () => {
    return gulp
        .src(hullScssPath + '/hull.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest(hullCompiledCSSPath))
});

gulp.task('hull-scripts', () => {
    return gulp
        .src(hullJSPath + '/**/*.js')
        .pipe(concat('hull.js'))
        .pipe(babel({
            presets: ['es2015']
        }))
        .pipe(gulp.dest(hullCompiledJSPath))
});

gulp.task('uglify-hull-styles', () => {
    return gulp
        .src(hullCompiledCSSPath + '/hull.css')
        .pipe(uglifycss())
        .pipe(rename('hull.min.css'))
        .pipe(gulp.dest(hullCompiledCSSPath))
});

gulp.task('uglify-hull-scripts', () => {
    return gulp
        .src(hullCompiledJSPath + '/hull.js')
        .pipe(uglify())
        .pipe(rename('hull.min.js'))
        .pipe(gulp.dest(hullCompiledJSPath))
});

/**
 * Launches All Watch Functions
 */
gulp.task('default', function () {
    gulp.watch(bridgeScssPath + '/**/*.scss', ['bridge-styles']);
    gulp.watch(bridgeJSPath   + '/**/*.js',   ['bridge-scripts']);
    gulp.watch(hullScssPath  + '/**/*.scss', ['hull-styles']);
    gulp.watch(hullJSPath    + '/**/*.js',   ['hull-scripts']);
});

/**
 * Build Commands For Each Section
 */
gulp.task('build-bridge', [
    'bridge-styles',
    'bridge-scripts',
    'uglify-bridge-styles',
    'uglify-bridge-scripts',
]);

gulp.task('build-hull', [
    'hull-styles',
    'hull-scripts',
    'uglify-hull-styles',
    'uglify-hull-scripts',
]);

gulp.task('build', [
    'build-bridge',
    'build-hull',
]);