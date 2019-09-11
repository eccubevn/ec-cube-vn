const gulp = require("gulp");
const $ = require("gulp-load-plugins")();
const sass = require('gulp-sass');
const rename = require('gulp-rename');
const cleanCSS = require('gulp-clean-css');
const es = require('event-stream');

const browserSync = require('browser-sync').create();
const php = require('gulp-connect-php');

const srcPattern = [
    'default',
    'admin'
];

// CSS出力
gulp.task('sass', function() {
    let events = srcPattern.map((target) => {
        return gulp.src(`./html/template/${target}/assets/scss/**/*.scss`)
            .pipe($.plumber({
                errorHandler: $.notify.onError('<%= error.message %>')
            }))
            .pipe($.sourcemaps.init())
            .pipe(sass({
                sourceMap: true
            }))
            .pipe($.pleeease({
                autoprefixer: true,
                minifier: false,
                mqpacker: true
            }))
            .pipe($.sourcemaps.write('./maps'))
            .pipe(gulp.dest(`./html/template/${target}/assets/css/`))
    });
    return es.concat(events);
});

// CSS圧縮(cssディレクトリ直下のファイルだけ対象とする)
gulp.task('minify-css', function() {
    let events = srcPattern.map((target) => {
        return gulp.src([`./html/template/${target}/assets/css/*.css`, `!./html/template/${target}/assets/css/**/*.min.css`])
            .pipe($.plumber({
                errorHandler: $.notify.onError('<%= error.message %>')
            }))
            .pipe($.sourcemaps.init())
            .pipe(sass({
                sourceMap: true
            }))
            .pipe(cleanCSS())
            .pipe(rename({
                extname: '.min.css'
            }))
            .pipe($.sourcemaps.write('./maps'))
            .pipe(gulp.dest(`./html/template/${target}/assets/css/`))
    });
    return es.concat(events);
});
gulp.task("default", ["sass", "minify-css"]);

// Custom build template
gulp.task('php', function(){
    php.server({
        base: './', 
        port: 8010, 
        keepalive: true
    });
});

gulp.task('browserSync',['php'], function() {
    browserSync.init({
        proxy: "localhost:8010",
        baseDir: "./",
        open: true,
        notify: false
    })
});

gulp.task('sass-frontend', function() {
    return gulp.src('./html/template/default/assets/scss/style.scss')
        .pipe($.plumber({
            errorHandler: $.notify.onError('<%= error.message %>')
        }))
        .pipe($.sourcemaps.init())
        .pipe(sass({
            sourceMap: true
        }))
        .pipe($.pleeease({
            autoprefixer: true,
            minifier: false,
            mqpacker: true
        }))
        .pipe($.sourcemaps.write('./maps'))
        .pipe(gulp.dest('./html/template/default/assets/css/'))
        .pipe(browserSync.reload({
            stream: true
        }))
});

// YOU ONLY NEED RUN 'gulp dev' AND CAN START DEVELOPMENT THE TEMPLATE
gulp.task('dev', ['browserSync', 'sass-frontend'], function(){
    gulp.watch('./html/template/default/assets/scss/**/*.scss', ['sass-frontend']); 
    gulp.watch('./app/template/default/**/*.twig', browserSync.reload); 
    gulp.watch('./html/template/default/assets/js/**/*.js', browserSync.reload); 
});